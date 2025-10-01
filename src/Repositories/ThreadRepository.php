<?php

namespace SimpleBBS\Repositories;

use SimpleBBS\Support\DatabaseManager;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

class ThreadRepository
{
    public function __construct(private readonly DatabaseManager $databaseManager)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByBoard(string $slug): array
    {
        $pdo = $this->databaseManager->getBoardConnection($slug);
        $stmt = $pdo->query(
            'SELECT t.id, t.title, t.created_at, t.updated_at, COUNT(p.id) AS posts_count
             FROM threads t
             LEFT JOIN posts p ON p.thread_id = t.id
             GROUP BY t.id
             ORDER BY t.updated_at DESC'
        );

        return $stmt ? $stmt->fetchAll() : [];
    }

    public function create(string $slug, string $title): int
    {
        $pdo = $this->databaseManager->getBoardConnection($slug);
        $now = new DateTimeImmutable('now');
        $stmt = $pdo->prepare('INSERT INTO threads (title, created_at, updated_at) VALUES (:title, :created_at, :updated_at)');
        $success = $stmt->execute([
            'title' => $title,
            'created_at' => $now->format(DateTimeImmutable::ATOM),
            'updated_at' => $now->format(DateTimeImmutable::ATOM),
        ]);

        if (!$success) {
            throw new RuntimeException('スレッドの作成に失敗しました。');
        }

        return (int)$pdo->lastInsertId();
    }

    public function find(string $slug, int $threadId): ?array
    {
        $pdo = $this->databaseManager->getBoardConnection($slug);
        $stmt = $pdo->prepare('SELECT id, title, created_at, updated_at FROM threads WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $threadId]);
        $thread = $stmt->fetch();

        return $thread ?: null;
    }

    public function addPost(string $slug, int $threadId, string $authorName, string $body): void
    {
        $pdo = $this->databaseManager->getBoardConnection($slug);
        $now = new DateTimeImmutable('now');
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('INSERT INTO posts (thread_id, author_name, body, created_at) VALUES (:thread_id, :author_name, :body, :created_at)');
            $stmt->execute([
                'thread_id' => $threadId,
                'author_name' => $authorName,
                'body' => $body,
                'created_at' => $now->format(DateTimeImmutable::ATOM),
            ]);

            $pdo->prepare('UPDATE threads SET updated_at = :updated_at WHERE id = :id')->execute([
                'updated_at' => $now->format(DateTimeImmutable::ATOM),
                'id' => $threadId,
            ]);

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPosts(string $slug, int $threadId): array
    {
        $pdo = $this->databaseManager->getBoardConnection($slug);
        $stmt = $pdo->prepare('SELECT id, author_name, body, created_at FROM posts WHERE thread_id = :thread_id ORDER BY created_at ASC');
        $stmt->execute(['thread_id' => $threadId]);

        return $stmt ? $stmt->fetchAll() : [];
    }
}

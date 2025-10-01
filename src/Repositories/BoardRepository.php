<?php

namespace SimpleBBS\Repositories;

use SimpleBBS\Support\DatabaseManager;
use DateTimeImmutable;
use RuntimeException;

class BoardRepository
{
    public function __construct(private readonly DatabaseManager $databaseManager)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $pdo = $this->databaseManager->getSystemConnection();
        $stmt = $pdo->query('SELECT id, slug, title, description, created_at FROM boards ORDER BY created_at DESC');

        return $stmt ? $stmt->fetchAll() : [];
    }

    public function findBySlug(string $slug): ?array
    {
        $pdo = $this->databaseManager->getSystemConnection();
        $stmt = $pdo->prepare('SELECT id, slug, title, description, created_at FROM boards WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => strtolower($slug)]);
        $board = $stmt->fetch();

        return $board ?: null;
    }

    public function create(string $title, string $slug, ?string $description): array
    {
        $pdo = $this->databaseManager->getSystemConnection();
        $now = new DateTimeImmutable('now');

        $stmt = $pdo->prepare('INSERT INTO boards (slug, title, description, created_at) VALUES (:slug, :title, :description, :created_at)');
        $success = $stmt->execute([
            'slug' => $slug,
            'title' => $title,
            'description' => $description,
            'created_at' => $now->format(DateTimeImmutable::ATOM),
        ]);

        if (!$success) {
            throw new RuntimeException('ボードの作成に失敗しました。');
        }

        $this->databaseManager->ensureBoardDatabase($slug);

        return $this->findBySlug($slug) ?? [];
    }
}

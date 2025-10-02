<?php

namespace SimpleBBS;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

class SimpleBBS
{
    protected string $storagePath;
    private ?PDO $systemConnection = null;
    /** @var array<string, PDO> */
    private array $boardConnections = [];

    public function __construct(?string $storagePath = null)
    {
        $packageRoot = dirname(__DIR__);
        $storagePath ??= $packageRoot . '/.storage';

        $this->storagePath = rtrim($storagePath, DIRECTORY_SEPARATOR);

        if (!is_dir($this->storagePath)) {
            if (!mkdir($this->storagePath, 0777, true) && !is_dir($this->storagePath)) {
                throw new RuntimeException('データディレクトリを作成できませんでした: ' . $this->storagePath);
            }
        }
    }

    public static function create(?string $storagePath = null): self
    {
        return new self($storagePath);
    }

    public function storagePath(): string
    {
        return $this->storagePath;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listBoards(): array
    {
        $statement = $this->systemConnection()->query(
            'SELECT slug, title, description, created_at, updated_at FROM boards ORDER BY updated_at DESC'
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getBoard(string $slug): array
    {
        $slug = $this->normaliseSlug($slug);
        if ($slug === '') {
            throw new InvalidArgumentException('ボードが指定されていません。');
        }

        $statement = $this->systemConnection()->prepare(
            'SELECT slug, title, description, created_at, updated_at FROM boards WHERE slug = :slug'
        );
        $statement->execute([':slug' => $slug]);
        $board = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$board) {
            throw new InvalidArgumentException('指定されたボードが見つかりません。');
        }

        $this->ensureBoardDatabase($slug);

        return $board;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listThreads(string $boardSlug): array
    {
        $board = $this->getBoard($boardSlug);
        $connection = $this->boardConnection($board['slug']);
        $query = <<<'SQL'
            SELECT
                id,
                title,
                created_at,
                updated_at,
                (
                    SELECT COUNT(*)
                    FROM posts
                    WHERE posts.thread_id = threads.id
                ) AS post_count
            FROM threads
            ORDER BY updated_at DESC
        SQL;

        $statement = $connection->query($query);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getThread(string $boardSlug, int $threadId): array
    {
        $board = $this->getBoard($boardSlug);
        $connection = $this->boardConnection($board['slug']);

        $threadStatement = $connection->prepare(
            'SELECT id, title, created_at, updated_at FROM threads WHERE id = :id'
        );
        $threadStatement->execute([':id' => $threadId]);
        $thread = $threadStatement->fetch(PDO::FETCH_ASSOC);

        if (!$thread) {
            throw new InvalidArgumentException('指定されたスレッドが見つかりません。');
        }

        $postsStatement = $connection->prepare(
            'SELECT id, thread_id, author_name, body, created_at FROM posts WHERE thread_id = :id ORDER BY id ASC'
        );
        $postsStatement->execute([':id' => $threadId]);
        $posts = $postsStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $thread['posts'] = $posts;

        return $thread;
    }

    public function createThread(string $boardSlug, string $title, ?string $authorName, string $body): int
    {
        $board = $this->getBoard($boardSlug);
        $title = trim($title);
        $body = trim($body);

        if ($title === '' || $body === '') {
            throw new InvalidArgumentException('スレッドのタイトルと本文を入力してください。');
        }

        $author = $this->filterAuthor($authorName);
        $connection = $this->boardConnection($board['slug']);
        $now = $this->now();

        $connection->beginTransaction();

        try {
            $insertThread = $connection->prepare(
                'INSERT INTO threads (title, created_at, updated_at) VALUES (:title, :created, :updated)'
            );
            $insertThread->execute([
                ':title' => $title,
                ':created' => $now,
                ':updated' => $now,
            ]);

            $threadId = (int)$connection->lastInsertId();
            $this->insertPost($connection, $threadId, $author, $body, $now);

            $connection->commit();

            $this->touchBoard($board['slug'], $now);

            return $threadId;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    public function addPost(string $boardSlug, int $threadId, ?string $authorName, string $body): void
    {
        $board = $this->getBoard($boardSlug);
        $body = trim($body);
        if ($body === '') {
            throw new InvalidArgumentException('本文を入力してください。');
        }

        $connection = $this->boardConnection($board['slug']);
        $author = $this->filterAuthor($authorName);
        $now = $this->now();

        $connection->beginTransaction();

        try {
            $this->fetchThreadRow($connection, $threadId);
            $this->insertPost($connection, $threadId, $author, $body, $now);
            $this->touchThread($connection, $threadId, $now);
            $connection->commit();

            $this->touchBoard($board['slug'], $now);
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    public function updatePost(string $boardSlug, int $threadId, int $postId, ?string $authorName, string $body): void
    {
        $board = $this->getBoard($boardSlug);
        $body = trim($body);
        if ($body === '') {
            throw new InvalidArgumentException('本文を入力してください。');
        }

        $connection = $this->boardConnection($board['slug']);
        $author = $this->filterAuthor($authorName);
        $now = $this->now();

        $connection->beginTransaction();

        try {
            $this->fetchThreadRow($connection, $threadId);
            $post = $this->fetchPostRow($connection, $postId);

            if ((int)$post['thread_id'] !== $threadId) {
                throw new InvalidArgumentException('指定された投稿が見つかりません。');
            }

            $statement = $connection->prepare(
                'UPDATE posts SET author_name = :author, body = :body WHERE id = :id'
            );
            $statement->execute([
                ':author' => $author,
                ':body' => $body,
                ':id' => $postId,
            ]);

            $this->touchThread($connection, $threadId, $now);
            $connection->commit();

            $this->touchBoard($board['slug'], $now);
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    protected function systemConnection(): PDO
    {
        if ($this->systemConnection instanceof PDO) {
            return $this->systemConnection;
        }

        $path = $this->storagePath . '/system.sqlite';
        $this->systemConnection = $this->createConnection($path);
        $this->initialiseSystemSchema($this->systemConnection);

        return $this->systemConnection;
    }

    protected function boardConnection(string $slug): PDO
    {
        $slug = $this->normaliseSlug($slug);
        if ($slug === '') {
            throw new InvalidArgumentException('無効なボードスラッグです。');
        }

        if (isset($this->boardConnections[$slug])) {
            return $this->boardConnections[$slug];
        }

        $boardsPath = $this->storagePath . '/boards';
        if (!is_dir($boardsPath)) {
            if (!mkdir($boardsPath, 0777, true) && !is_dir($boardsPath)) {
                throw new RuntimeException('ボード用データディレクトリを作成できませんでした: ' . $boardsPath);
            }
        }

        $path = $boardsPath . '/' . $slug . '.sqlite';
        $connection = $this->createConnection($path);
        $this->initialiseBoardSchema($connection);

        $this->boardConnections[$slug] = $connection;

        return $connection;
    }

    protected function ensureBoardDatabase(string $slug): void
    {
        $this->boardConnection($slug);
    }

    protected function normaliseSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9\-]+/u', '-', $slug);

        return trim((string)$slug, '-');
    }

    protected function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    protected function filterAuthor(?string $authorName): string
    {
        $name = trim((string)$authorName);

        return $name !== '' ? $name : '名無しさん';
    }

    protected function touchBoard(string $slug, string $timestamp): void
    {
        $statement = $this->systemConnection()->prepare(
            'UPDATE boards SET updated_at = :updated WHERE slug = :slug'
        );
        $statement->execute([
            ':updated' => $timestamp,
            ':slug' => $slug,
        ]);
    }

    private function createConnection(string $path): PDO
    {
        try {
            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA foreign_keys = ON');

            return $pdo;
        } catch (PDOException $exception) {
            throw new RuntimeException('SQLite接続に失敗しました: ' . $exception->getMessage(), 0, $exception);
        }
    }

    private function initialiseSystemSchema(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS boards (
                slug TEXT PRIMARY KEY,
                title TEXT NOT NULL,
                description TEXT DEFAULT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );

        $this->ensureColumnExists($pdo, 'boards', 'updated_at', 'TEXT');
        $this->backfillBoardUpdatedAt($pdo);
    }

    private function initialiseBoardSchema(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS threads (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );

        $this->ensureColumnExists($pdo, 'threads', 'updated_at', 'TEXT');
        $this->backfillThreadUpdatedAt($pdo);

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                thread_id INTEGER NOT NULL,
                author_name TEXT NOT NULL,
                body TEXT NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY(thread_id) REFERENCES threads(id) ON DELETE CASCADE
            )'
        );
    }

    private function insertPost(PDO $connection, int $threadId, string $author, string $body, string $createdAt): void
    {
        $statement = $connection->prepare(
            'INSERT INTO posts (thread_id, author_name, body, created_at) VALUES (:thread, :author, :body, :created)'
        );
        $statement->execute([
            ':thread' => $threadId,
            ':author' => $author,
            ':body' => $body,
            ':created' => $createdAt,
        ]);
    }

    private function ensureColumnExists(PDO $pdo, string $table, string $column, string $definition): bool
    {
        $statement = $pdo->query('PRAGMA table_info(' . $this->quoteIdentifier($table) . ')');
        $columns = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $info) {
            if (isset($info['name']) && strcasecmp($info['name'], $column) === 0) {
                return true;
            }
        }

        $pdo->exec(sprintf(
            'ALTER TABLE %s ADD COLUMN %s %s',
            $this->quoteIdentifier($table),
            $this->quoteIdentifier($column),
            $definition
        ));

        return false;
    }

    private function backfillBoardUpdatedAt(PDO $pdo): void
    {
        $pdo->exec("UPDATE boards SET updated_at = created_at WHERE updated_at IS NULL OR updated_at = ''");
    }

    private function backfillThreadUpdatedAt(PDO $pdo): void
    {
        $pdo->exec("UPDATE threads SET updated_at = created_at WHERE updated_at IS NULL OR updated_at = ''");
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private function touchThread(PDO $connection, int $threadId, string $timestamp): void
    {
        $statement = $connection->prepare('UPDATE threads SET updated_at = :updated WHERE id = :id');
        $statement->execute([
            ':updated' => $timestamp,
            ':id' => $threadId,
        ]);
    }

    private function fetchThreadRow(PDO $connection, int $threadId): array
    {
        $statement = $connection->prepare('SELECT id FROM threads WHERE id = :id');
        $statement->execute([':id' => $threadId]);
        $thread = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$thread) {
            throw new InvalidArgumentException('指定されたスレッドが見つかりません。');
        }

        return $thread;
    }

    private function fetchPostRow(PDO $connection, int $postId): array
    {
        $statement = $connection->prepare('SELECT id, thread_id FROM posts WHERE id = :id');
        $statement->execute([':id' => $postId]);
        $post = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$post) {
            throw new InvalidArgumentException('指定された投稿が見つかりません。');
        }

        return $post;
    }
}

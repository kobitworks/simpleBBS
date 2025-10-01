<?php

namespace App\Support;

use PDO;
use PDOException;
use RuntimeException;

class DatabaseManager
{
    private ?PDO $systemConnection = null;

    public function __construct(private readonly string $dataPath)
    {
        if (!is_dir($this->dataPath)) {
            if (!mkdir($this->dataPath, 0777, true) && !is_dir($this->dataPath)) {
                throw new RuntimeException('データディレクトリを作成できませんでした: ' . $this->dataPath);
            }
        }
    }

    public function getSystemConnection(): PDO
    {
        if ($this->systemConnection instanceof PDO) {
            return $this->systemConnection;
        }

        $path = $this->dataPath . '/system.sqlite';
        $this->systemConnection = $this->createConnection($path);
        $this->initialiseSystemSchema($this->systemConnection);

        return $this->systemConnection;
    }

    public function getBoardConnection(string $slug): PDO
    {
        $slug = $this->normaliseSlug($slug);
        $path = $this->getBoardDatabasePath($slug);
        $pdo = $this->createConnection($path);
        $this->initialiseBoardSchema($pdo);

        return $pdo;
    }

    public function getBoardDatabasePath(string $slug): string
    {
        $slug = $this->normaliseSlug($slug);
        $boardsPath = $this->dataPath . '/boards';
        if (!is_dir($boardsPath)) {
            if (!mkdir($boardsPath, 0777, true) && !is_dir($boardsPath)) {
                throw new RuntimeException('ボード用データディレクトリを作成できませんでした: ' . $boardsPath);
            }
        }

        return $boardsPath . '/' . $slug . '.sqlite';
    }

    public function ensureBoardDatabase(string $slug): void
    {
        $this->getBoardConnection($slug);
    }

    private function createConnection(string $path): PDO
    {
        try {
            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            return $pdo;
        } catch (PDOException $exception) {
            throw new RuntimeException('SQLite接続に失敗しました: ' . $exception->getMessage(), 0, $exception);
        }
    }

    private function initialiseSystemSchema(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS boards (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                title TEXT NOT NULL,
                description TEXT DEFAULT NULL,
                created_at TEXT NOT NULL
            )'
        );
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

    private function normaliseSlug(string $slug): string
    {
        return strtolower(trim($slug));
    }
}

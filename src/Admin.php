<?php

namespace SimpleBBS;

use InvalidArgumentException;

class Admin extends SimpleBBS
{
    public function createBoard(string $title, ?string $slug = null, ?string $description = null): array
    {
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException('ボード名を入力してください。');
        }

        $slug = $this->buildSlug($title, $slug);
        $description = $description !== null ? trim($description) : null;

        $connection = $this->systemConnection();
        $exists = $connection->prepare('SELECT COUNT(*) FROM boards WHERE slug = :slug');
        $exists->execute([':slug' => $slug]);

        if ((int)$exists->fetchColumn() > 0) {
            throw new InvalidArgumentException('同じスラッグのボードが既に存在します。');
        }

        $now = $this->now();
        $insert = $connection->prepare(
            'INSERT INTO boards (slug, title, description, created_at, updated_at) VALUES (:slug, :title, :description, :created, :updated)'
        );
        $insert->execute([
            ':slug' => $slug,
            ':title' => $title,
            ':description' => $description !== '' ? $description : null,
            ':created' => $now,
            ':updated' => $now,
        ]);

        $this->ensureBoardDatabase($slug);

        return $this->getBoard($slug);
    }

    public function updateBoard(string $slug, string $title, ?string $description = null): array
    {
        $board = $this->getBoard($slug);
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException('ボード名を入力してください。');
        }

        $description = $description !== null ? trim($description) : null;

        $connection = $this->systemConnection();
        $statement = $connection->prepare(
            'UPDATE boards SET title = :title, description = :description, updated_at = :updated WHERE slug = :slug'
        );
        $statement->execute([
            ':title' => $title,
            ':description' => $description !== '' ? $description : null,
            ':updated' => $this->now(),
            ':slug' => $board['slug'],
        ]);

        return $this->getBoard($board['slug']);
    }

    public function deleteBoard(string $slug): void
    {
        $board = $this->getBoard($slug);
        $connection = $this->systemConnection();
        $statement = $connection->prepare('DELETE FROM boards WHERE slug = :slug');
        $statement->execute([':slug' => $board['slug']]);

        $databasePath = $this->storagePath() . '/boards/' . $board['slug'] . '.sqlite';
        if (is_file($databasePath)) {
            @unlink($databasePath);
        }
    }

    private function buildSlug(string $title, ?string $slug): string
    {
        $source = $slug !== null && trim($slug) !== '' ? $slug : $title;
        $normalised = $this->normaliseSlug($source);

        if ($normalised === '') {
            throw new InvalidArgumentException('有効なスラッグを指定してください。');
        }

        return $normalised;
    }
}

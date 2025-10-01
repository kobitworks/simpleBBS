<?php

namespace SimpleBBS\Management;

use SimpleBBS\Support\DatabaseManager;

/**
 * ストレージやデータベースなど、システム全体の管理機能を提供します。
 */
class SystemManager
{
    public function __construct(private readonly DatabaseManager $databaseManager)
    {
    }

    public function storagePath(): string
    {
        return $this->databaseManager->getDataPath();
    }

    public function boardDatabasePath(string $slug): string
    {
        return $this->databaseManager->getBoardDatabasePath($slug);
    }

    public function ensureBoardStorage(string $slug): void
    {
        $this->databaseManager->ensureBoardDatabase($slug);
    }

    public function databaseManager(): DatabaseManager
    {
        return $this->databaseManager;
    }
}

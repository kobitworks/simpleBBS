<?php

namespace SimpleBBS\Threads;

use SimpleBBS\Services\ThreadService;

/**
 * スレッドおよび投稿操作を提供するファサードクラス。
 */
class ThreadManager
{
    public function __construct(private readonly ThreadService $service)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listThreads(string $boardSlug): array
    {
        return $this->service->listThreads($boardSlug);
    }

    public function createThread(string $boardSlug, string $title, string $authorName, string $body): int
    {
        return $this->service->createThread($boardSlug, $title, $authorName, $body);
    }

    public function addPost(string $boardSlug, int $threadId, string $authorName, string $body): void
    {
        $this->service->addPost($boardSlug, $threadId, $authorName, $body);
    }

    public function getThread(string $boardSlug, int $threadId): array
    {
        return $this->service->getThread($boardSlug, $threadId);
    }

    public function service(): ThreadService
    {
        return $this->service;
    }
}

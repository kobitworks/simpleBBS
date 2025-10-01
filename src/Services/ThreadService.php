<?php

namespace SimpleBBS\Services;

use SimpleBBS\Repositories\ThreadRepository;
use InvalidArgumentException;

class ThreadService
{
    public function __construct(private readonly ThreadRepository $repository)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listThreads(string $boardSlug): array
    {
        return $this->repository->listByBoard($boardSlug);
    }

    public function createThread(string $boardSlug, string $title, string $authorName, string $body): int
    {
        $title = trim($title);
        $authorName = trim($authorName);
        $body = trim($body);

        if ($title === '' || $authorName === '' || $body === '') {
            throw new InvalidArgumentException('スレッドのタイトル、投稿者、本文は必須です。');
        }

        $threadId = $this->repository->create($boardSlug, $title);
        $this->repository->addPost($boardSlug, $threadId, $authorName, $body);

        return $threadId;
    }

    public function addPost(string $boardSlug, int $threadId, string $authorName, string $body): void
    {
        $authorName = trim($authorName);
        $body = trim($body);

        if ($authorName === '' || $body === '') {
            throw new InvalidArgumentException('投稿者と本文は必須です。');
        }

        $this->repository->addPost($boardSlug, $threadId, $authorName, $body);
    }

    public function getThread(string $boardSlug, int $threadId): array
    {
        $thread = $this->repository->find($boardSlug, $threadId);

        if (!$thread) {
            throw new InvalidArgumentException('指定されたスレッドが見つかりません。');
        }

        $posts = $this->repository->listPosts($boardSlug, $threadId);
        $thread['posts'] = $posts;

        return $thread;
    }
}

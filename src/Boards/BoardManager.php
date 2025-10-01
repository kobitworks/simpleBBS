<?php

namespace SimpleBBS\Boards;

use SimpleBBS\Services\BoardService;

/**
 * ボードに関するユースケースを取り扱うファサードクラス。
 *
 * コントローラや他システムから直接呼び出しやすいように、
 * BoardService の公開メソッドを委譲しています。
 */
class BoardManager
{
    public function __construct(private readonly BoardService $service)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listBoards(): array
    {
        return $this->service->listBoards();
    }

    public function getBoard(string $slug): array
    {
        return $this->service->getBoard($slug);
    }

    public function createBoard(string $title, ?string $slug, ?string $description): array
    {
        return $this->service->createBoard($title, $slug, $description);
    }

    public function service(): BoardService
    {
        return $this->service;
    }
}

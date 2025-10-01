<?php

namespace App\Services;

use App\Repositories\BoardRepository;
use InvalidArgumentException;

class BoardService
{
    public function __construct(private readonly BoardRepository $repository)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listBoards(): array
    {
        return $this->repository->all();
    }

    public function getBoard(string $slug): array
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            throw new InvalidArgumentException('ボードが指定されていません。');
        }

        $board = $this->repository->findBySlug($slug);
        if (!$board) {
            throw new InvalidArgumentException('指定されたボードが見つかりません。');
        }

        return $board;
    }

    public function createBoard(string $title, ?string $slug, ?string $description): array
    {
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException('ボード名を入力してください。');
        }

        $slug = $this->buildSlug($title, $slug);
        $description = $description !== null ? trim($description) : null;

        if ($this->repository->findBySlug($slug)) {
            throw new InvalidArgumentException('同じスラッグのボードが既に存在します。');
        }

        return $this->repository->create($title, $slug, $description);
    }

    private function buildSlug(string $title, ?string $slug): string
    {
        $base = $slug ? $slug : $title;
        $normalised = strtolower(trim($base));
        $normalised = preg_replace('/[^a-z0-9\-]+/u', '-', $normalised ?? '');
        $normalised = trim((string)$normalised, '-');

        if ($normalised === '') {
            throw new InvalidArgumentException('有効なスラッグを指定してください。');
        }

        return $normalised;
    }
}

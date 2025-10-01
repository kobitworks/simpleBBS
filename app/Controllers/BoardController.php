<?php

namespace App\Controllers;

use App\Http\Request;
use App\Services\BoardService;
use App\Services\ThreadService;
use InvalidArgumentException;
use Twig\Environment;

class BoardController
{
    public function __construct(
        private readonly Environment $view,
        private readonly BoardService $boardService,
        private readonly ThreadService $threadService
    ) {
    }

    public function index(Request $request): string
    {
        $boards = $this->boardService->listBoards();

        return $this->view->render('boards/index.twig', [
            'boards' => $boards,
            'errors' => [],
        ]);
    }

    public function store(Request $request): void
    {
        try {
            $board = $this->boardService->createBoard(
                (string)$request->input('title'),
                $request->input('slug'),
                $request->input('description')
            );

            header('Location: ?route=boards.show&slug=' . urlencode($board['slug']));
            exit;
        } catch (InvalidArgumentException $exception) {
            $boards = $this->boardService->listBoards();
            echo $this->view->render('boards/index.twig', [
                'boards' => $boards,
                'errors' => [$exception->getMessage()],
                'old' => [
                    'title' => $request->input('title'),
                    'slug' => $request->input('slug'),
                    'description' => $request->input('description'),
                ],
            ]);
        }
    }

    public function show(Request $request): string
    {
        $slug = (string)$request->query('slug');
        $board = $this->boardService->getBoard($slug);
        $threads = $this->threadService->listThreads($board['slug']);

        return $this->view->render('boards/show.twig', [
            'board' => $board,
            'threads' => $threads,
            'errors' => [],
        ]);
    }
}

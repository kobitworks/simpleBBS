<?php

namespace SimpleBBS\Controllers;

use SimpleBBS\Boards\BoardManager;
use SimpleBBS\Http\Request;
use SimpleBBS\Support\Config;
use SimpleBBS\Threads\ThreadManager;
use InvalidArgumentException;
use Twig\Environment;

class BoardController
{
    public function __construct(
        private readonly Environment $view,
        private readonly BoardManager $boardManager,
        private readonly ThreadManager $threadManager,
        private readonly Config $config
    ) {
    }

    public function index(Request $request): string
    {
        $boards = $this->boardManager->listBoards();

        return $this->view->render('boards/index.twig', [
            'boards' => $boards,
            'errors' => [],
        ]);
    }

    public function store(Request $request): void
    {
        if (!$this->config->allowsUserBoardCreation()) {
            http_response_code(403);
            $boards = $this->boardManager->listBoards();
            echo $this->view->render('boards/index.twig', [
                'boards' => $boards,
                'errors' => ['現在は新しいボードを作成できません。'],
            ]);
            return;
        }

        if ($request->user() === null) {
            http_response_code(403);
            $boards = $this->boardManager->listBoards();
            echo $this->view->render('boards/index.twig', [
                'boards' => $boards,
                'errors' => ['ボードを作成するにはログインが必要です。'],
            ]);
            return;
        }

        try {
            $board = $this->boardManager->createBoard(
                (string)$request->input('title'),
                $request->input('slug'),
                $request->input('description')
            );

            header('Location: ?route=boards.show&slug=' . urlencode($board['slug']));
            exit;
        } catch (InvalidArgumentException $exception) {
            $boards = $this->boardManager->listBoards();
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
        $board = $this->boardManager->getBoard($slug);
        $threads = $this->threadManager->listThreads($board['slug']);

        return $this->view->render('boards/show.twig', [
            'board' => $board,
            'threads' => $threads,
            'errors' => [],
        ]);
    }
}

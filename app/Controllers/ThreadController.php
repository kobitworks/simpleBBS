<?php

namespace App\Controllers;

use App\Http\Request;
use App\Services\BoardService;
use App\Services\ThreadService;
use InvalidArgumentException;
use Twig\Environment;

class ThreadController
{
    public function __construct(
        private readonly Environment $view,
        private readonly BoardService $boardService,
        private readonly ThreadService $threadService
    ) {
    }

    public function create(Request $request): void
    {
        $slug = (string)$request->query('slug');
        $board = $this->boardService->getBoard($slug);

        try {
            $threadId = $this->threadService->createThread(
                $board['slug'],
                (string)$request->input('title'),
                (string)$request->input('author_name'),
                (string)$request->input('body')
            );

            header('Location: ?route=threads.show&slug=' . urlencode($board['slug']) . '&thread=' . $threadId);
            exit;
        } catch (InvalidArgumentException $exception) {
            $threads = $this->threadService->listThreads($board['slug']);
            echo $this->view->render('boards/show.twig', [
                'board' => $board,
                'threads' => $threads,
                'errors' => [$exception->getMessage()],
                'old' => [
                    'thread' => [
                        'title' => $request->input('title'),
                        'author_name' => $request->input('author_name'),
                        'body' => $request->input('body'),
                    ],
                ],
            ]);
        }
    }

    public function show(Request $request): string
    {
        $slug = (string)$request->query('slug');
        $threadId = (int)$request->query('thread');

        $board = $this->boardService->getBoard($slug);
        $thread = $this->threadService->getThread($board['slug'], $threadId);

        return $this->view->render('threads/show.twig', [
            'board' => $board,
            'thread' => $thread,
            'errors' => [],
        ]);
    }

    public function storePost(Request $request): void
    {
        $slug = (string)$request->query('slug');
        $threadId = (int)$request->query('thread');
        $board = $this->boardService->getBoard($slug);

        try {
            $this->threadService->addPost(
                $board['slug'],
                $threadId,
                (string)$request->input('author_name'),
                (string)$request->input('body')
            );

            header('Location: ?route=threads.show&slug=' . urlencode($board['slug']) . '&thread=' . $threadId);
            exit;
        } catch (InvalidArgumentException $exception) {
            $thread = $this->threadService->getThread($board['slug'], $threadId);
            echo $this->view->render('threads/show.twig', [
                'board' => $board,
                'thread' => $thread,
                'errors' => [$exception->getMessage()],
                'old' => [
                    'author_name' => $request->input('author_name'),
                    'body' => $request->input('body'),
                ],
            ]);
        }
    }
}

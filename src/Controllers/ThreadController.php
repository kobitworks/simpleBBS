<?php

namespace SimpleBBS\Controllers;

use SimpleBBS\Boards\BoardManager;
use SimpleBBS\Http\Request;
use SimpleBBS\Threads\ThreadManager;
use InvalidArgumentException;
use Twig\Environment;

class ThreadController
{
    public function __construct(
        private readonly Environment $view,
        private readonly BoardManager $boardManager,
        private readonly ThreadManager $threadManager
    ) {
    }

    public function create(Request $request): void
    {
        $slug = (string)$request->query('slug');
        $board = $this->boardManager->getBoard($slug);

        try {
            $threadId = $this->threadManager->createThread(
                $board['slug'],
                (string)$request->input('title'),
                (string)$request->input('author_name'),
                (string)$request->input('body')
            );

            header('Location: ?route=threads.show&slug=' . urlencode($board['slug']) . '&thread=' . $threadId);
            exit;
        } catch (InvalidArgumentException $exception) {
            $threads = $this->threadManager->listThreads($board['slug']);
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

        $board = $this->boardManager->getBoard($slug);
        $thread = $this->threadManager->getThread($board['slug'], $threadId);

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
        $board = $this->boardManager->getBoard($slug);

        try {
            $this->threadManager->addPost(
                $board['slug'],
                $threadId,
                (string)$request->input('author_name'),
                (string)$request->input('body')
            );

            header('Location: ?route=threads.show&slug=' . urlencode($board['slug']) . '&thread=' . $threadId);
            exit;
        } catch (InvalidArgumentException $exception) {
            $thread = $this->threadManager->getThread($board['slug'], $threadId);
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

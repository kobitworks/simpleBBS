<?php

namespace SimpleBBS\Controllers;

use SimpleBBS\Boards\BoardManager;
use SimpleBBS\Http\Request;
use SimpleBBS\Support\Config;
use SimpleBBS\Threads\ThreadManager;
use InvalidArgumentException;
use Twig\Environment;

class ThreadController
{
    public function __construct(
        private readonly Environment $view,
        private readonly BoardManager $boardManager,
        private readonly ThreadManager $threadManager,
        private readonly Config $config
    ) {
    }

    public function create(Request $request): void
    {
        $slug = (string)$request->query('slug');
        $board = $this->boardManager->getBoard($slug);
        $user = $request->user();
        $authorName = $user?->getName();

        if (!$user && !$this->config->allowsAnonymousPosting()) {
            http_response_code(403);
            $threads = $this->threadManager->listThreads($board['slug']);
            echo $this->view->render('boards/show.twig', [
                'board' => $board,
                'threads' => $threads,
                'errors' => ['スレッドを作成するにはログインが必要です。'],
                'old' => [
                    'thread' => [
                        'title' => $request->input('title'),
                        'body' => $request->input('body'),
                        'author_name' => $request->input('author_name'),
                    ],
                ],
            ]);
            return;
        }

        if (!$authorName) {
            $authorName = $this->resolveAuthorName($request->input('author_name'));
        }

        try {
            $threadId = $this->threadManager->createThread(
                $board['slug'],
                (string)$request->input('title'),
                $authorName,
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
                        'body' => $request->input('body'),
                        'author_name' => $request->input('author_name'),
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
        $user = $request->user();
        $authorName = $user?->getName();

        if (!$user && !$this->config->allowsAnonymousPosting()) {
            http_response_code(403);
            $thread = $this->threadManager->getThread($board['slug'], $threadId);
            echo $this->view->render('threads/show.twig', [
                'board' => $board,
                'thread' => $thread,
                'errors' => ['投稿するにはログインが必要です。'],
                'old' => [
                    'body' => $request->input('body'),
                    'author_name' => $request->input('author_name'),
                ],
            ]);
            return;
        }

        if (!$authorName) {
            $authorName = $this->resolveAuthorName($request->input('author_name'));
        }

        try {
            $this->threadManager->addPost(
                $board['slug'],
                $threadId,
                $authorName,
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
                    'body' => $request->input('body'),
                    'author_name' => $request->input('author_name'),
                ],
            ]);
        }
    }

    private function resolveAuthorName(mixed $authorName): string
    {
        $name = trim((string)$authorName);

        return $name !== '' ? $name : '名無しさん';
    }
}

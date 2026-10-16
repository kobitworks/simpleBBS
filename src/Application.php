<?php

namespace SimpleBBS;

use InvalidArgumentException;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class Application
{
    private Environment $view;

    public function __construct(
        private readonly SimpleBBS $bbs,
        private readonly Admin $admin,
        ?Environment $view = null,
        ?string $viewsPath = null,
    ) {
        $viewsPath ??= dirname(__DIR__) . '/resources/views';
        $this->view = $view ?? new Environment(new FilesystemLoader($viewsPath), [
            'cache' => false,
        ]);
    }

    public static function create(
        ?string $storagePath = null,
        ?Environment $view = null,
        ?string $viewsPath = null,
    ): self {
        $bbs = new SimpleBBS($storagePath);
        $admin = new Admin($storagePath);

        return new self($bbs, $admin, $view, $viewsPath);
    }

    public function handle(): void
    {
        $route = (string)($_GET['route'] ?? 'boards');
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        try {
            $this->initialiseRequestSecurity();

            if ($method === 'POST') {
                if (!$this->isValidCsrfToken()) {
                    $this->forbidden('リクエストを検証できませんでした。ページを再読み込みしてください。');
                    return;
                }
                if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768) {
                    http_response_code(413);
                    echo $this->view->render('error.twig', ['message' => '送信内容が大きすぎます。']);
                    return;
                }
                $this->handlePost($route);

                return;
            }

            $this->handleGet($route);
        } catch (InvalidArgumentException $exception) {
            http_response_code(400);
            echo $this->view->render('error.twig', [
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            error_log('simpleBBS request failed: ' . $exception->getMessage());
            http_response_code(500);
            echo $this->view->render('error.twig', [
                'message' => 'アプリケーションエラーが発生しました。',
            ]);
        }
    }

    private function handleGet(string $route, array $context = []): void
    {
        switch ($route) {
            case 'boards':
                $this->renderBoards($context);
                break;
            case 'board':
                $slug = (string)($_GET['slug'] ?? '');
                if ($slug === '') {
                    http_response_code(400);
                    echo $this->view->render('error.twig', [
                        'message' => 'ボードが指定されていません。',
                    ]);
                    return;
                }
                $this->renderBoard($slug, $context);
                break;
            case 'thread':
                $slug = (string)($_GET['slug'] ?? '');
                $threadId = (int)($_GET['thread'] ?? 0);
                if ($slug === '' || $threadId <= 0) {
                    http_response_code(400);
                    echo $this->view->render('error.twig', [
                        'message' => 'スレッドが指定されていません。',
                    ]);
                    return;
                }
                $editPost = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
                if ($editPost !== null && !$this->ownsPost($slug, $editPost)) {
                    $editPost = null;
                }
                $this->renderThread($slug, $threadId, $context, $editPost);
                break;
            default:
                http_response_code(404);
                echo $this->view->render('error.twig', [
                    'message' => '指定されたページは存在しません。',
                ]);
        }
    }

    private function handlePost(string $route): void
    {
        switch ($route) {
            case 'board_create':
                $this->handleBoardCreate();
                break;
            case 'thread_create':
                $this->handleThreadCreate();
                break;
            case 'post_create':
                $this->handlePostCreate();
                break;
            case 'post_update':
                $this->handlePostUpdate();
                break;
            default:
                http_response_code(404);
                echo $this->view->render('error.twig', [
                    'message' => '指定された操作は存在しません。',
                ]);
        }
    }

    private function handleBoardCreate(): void
    {
        $adminToken = getenv('SIMPLEBBS_ADMIN_TOKEN');
        $providedToken = (string)($_POST['admin_token'] ?? '');
        if (!is_string($adminToken) || strlen($adminToken) < 32 ||
            !hash_equals($adminToken, $providedToken)) {
            $this->forbidden('ボードの作成には管理者の認証が必要です。');
            return;
        }

        $title = (string)($_POST['title'] ?? '');
        $slug = $_POST['slug'] ?? null;
        $description = $_POST['description'] ?? null;

        try {
            $board = $this->admin->createBoard($title, $slug, $description);
            header('Location: ?route=board&slug=' . urlencode($board['slug']));
            exit;
        } catch (InvalidArgumentException $exception) {
            http_response_code(422);
            $this->renderBoards([
                'errors' => [$exception->getMessage()],
                'old' => [
                    'title' => $title,
                    'slug' => (string)($slug ?? ''),
                    'description' => (string)($description ?? ''),
                ],
            ]);
        }
    }

    private function handleThreadCreate(): void
    {
        $slug = (string)($_GET['slug'] ?? '');
        if ($slug === '') {
            http_response_code(400);
            echo $this->view->render('error.twig', [
                'message' => 'ボードが指定されていません。',
            ]);
            return;
        }

        $title = (string)($_POST['title'] ?? '');
        $author = $_POST['author_name'] ?? null;
        $body = (string)($_POST['body'] ?? '');

        try {
            $threadId = $this->bbs->createThread($slug, $title, $author, $body);
            $thread = $this->bbs->getThread($slug, $threadId);
            $firstPostId = (int)($thread['posts'][0]['id'] ?? 0);
            if ($firstPostId > 0) {
                $this->markOwnedPost($slug, $firstPostId);
            }
            header('Location: ?route=thread&slug=' . urlencode($slug) . '&thread=' . $threadId);
            exit;
        } catch (InvalidArgumentException $exception) {
            http_response_code(422);
            $this->renderBoard($slug, [
                'errors' => [$exception->getMessage()],
                'old' => [
                    'thread' => [
                        'title' => $title,
                        'author_name' => (string)($author ?? ''),
                        'body' => $body,
                    ],
                ],
            ]);
        }
    }

    private function handlePostCreate(): void
    {
        $slug = (string)($_GET['slug'] ?? '');
        $threadId = (int)($_GET['thread'] ?? 0);
        if ($slug === '' || $threadId <= 0) {
            http_response_code(400);
            echo $this->view->render('error.twig', [
                'message' => '投稿先のスレッドが指定されていません。',
            ]);
            return;
        }

        $author = $_POST['author_name'] ?? null;
        $body = (string)($_POST['body'] ?? '');

        try {
            $newPostId = $this->bbs->addPost($slug, $threadId, $author, $body);
            $this->markOwnedPost($slug, $newPostId);
            header('Location: ?route=thread&slug=' . urlencode($slug) . '&thread=' . $threadId);
            exit;
        } catch (InvalidArgumentException $exception) {
            http_response_code(422);
            $this->renderThread($slug, $threadId, [
                'errors' => [$exception->getMessage()],
                'old' => [
                    'reply' => [
                        'author_name' => (string)($author ?? ''),
                        'body' => $body,
                    ],
                ],
            ]);
        }
    }

    private function handlePostUpdate(): void
    {
        $slug = (string)($_GET['slug'] ?? '');
        $threadId = (int)($_GET['thread'] ?? 0);
        $postId = (int)($_GET['post'] ?? 0);
        if ($slug === '' || $threadId <= 0 || $postId <= 0) {
            http_response_code(400);
            echo $this->view->render('error.twig', [
                'message' => '編集対象の投稿が指定されていません。',
            ]);
            return;
        }

        if (!$this->ownsPost($slug, $postId)) {
            $this->forbidden('この投稿を編集する権限がありません。');
            return;
        }

        $author = $_POST['author_name'] ?? null;
        $body = (string)($_POST['body'] ?? '');

        try {
            $this->bbs->updatePost($slug, $threadId, $postId, $author, $body);
            header('Location: ?route=thread&slug=' . urlencode($slug) . '&thread=' . $threadId);
            exit;
        } catch (InvalidArgumentException $exception) {
            http_response_code(422);
            $this->renderThread($slug, $threadId, [
                'errors' => [$exception->getMessage()],
                'old' => [
                    'post' => [
                        'id' => $postId,
                        'author_name' => (string)($author ?? ''),
                        'body' => $body,
                    ],
                ],
            ], $postId);
        }
    }

    private function renderBoards(array $context = []): void
    {
        echo $this->view->render('boards.twig', [
            'boards' => $this->bbs->listBoards(),
            'errors' => $context['errors'] ?? [],
            'old' => $context['old'] ?? [],
        ]);
    }

    private function renderBoard(string $slug, array $context = []): void
    {
        $board = $this->bbs->getBoard($slug);
        echo $this->view->render('board.twig', [
            'board' => $board,
            'threads' => $this->bbs->listThreads($board['slug']),
            'errors' => $context['errors'] ?? [],
            'old' => $context['old'] ?? [],
        ]);
    }

    private function renderThread(string $slug, int $threadId, array $context = [], ?int $editingPostId = null): void
    {
        $board = $this->bbs->getBoard($slug);
        echo $this->view->render('thread.twig', [
            'board' => $board,
            'thread' => $this->bbs->getThread($board['slug'], $threadId),
            'errors' => $context['errors'] ?? [],
            'old' => $context['old'] ?? [],
            'editingPostId' => $editingPostId,
            'ownedPostIds' => $_SESSION['simplebbs_owned_posts'][$board['slug']] ?? [],
        ]);
    }

    /**
     * Use a per-browser session for CSRF and anonymous post ownership.
     * Posts from earlier sessions cannot be edited without an authenticated account.
     */
    private function initialiseRequestSecurity(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            session_name('simplebbs_session');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $https,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            if (!session_start()) {
                throw new \RuntimeException('Could not start a secure session');
            }
        }
        if (empty($_SESSION['simplebbs_csrf']) || !is_string($_SESSION['simplebbs_csrf'])) {
            $_SESSION['simplebbs_csrf'] = bin2hex(random_bytes(32));
        }
        $this->view->addGlobal('csrfToken', $_SESSION['simplebbs_csrf']);
        $adminToken = getenv('SIMPLEBBS_ADMIN_TOKEN');
        $this->view->addGlobal('canCreateBoards', is_string($adminToken) && strlen($adminToken) >= 32);
    }

    private function isValidCsrfToken(): bool
    {
        $provided = $_POST['csrf_token'] ?? null;
        return is_string($provided)
            && is_string($_SESSION['simplebbs_csrf'] ?? null)
            && hash_equals($_SESSION['simplebbs_csrf'], $provided);
    }

    private function ownsPost(string $slug, int $postId): bool
    {
        return $postId > 0 && !empty($_SESSION['simplebbs_owned_posts'][$slug][(string)$postId]);
    }

    private function markOwnedPost(string $slug, int $postId): void
    {
        $_SESSION['simplebbs_owned_posts'][$slug][(string)$postId] = true;
    }

    private function forbidden(string $message): void
    {
        http_response_code(403);
        echo $this->view->render('error.twig', ['message' => $message]);
    }

}

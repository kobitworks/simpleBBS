<?php

namespace SimpleBBS;

use SimpleBBS\Controllers\BoardController;
use SimpleBBS\Controllers\ThreadController;
use SimpleBBS\Core\Router;
use SimpleBBS\Http\Request;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class Application
{
    private Router $router;
    private Environment $view;
    private string $viewsPath;
    private SimpleBBS $bbs;

    public function __construct(
        private readonly string $storagePath,
        ?Environment $view = null,
        ?string $viewsPath = null,
        ?SimpleBBS $bbs = null
    ) {
        $this->viewsPath = $viewsPath ?? dirname(__DIR__) . '/resources/views';
        $this->view = $view ?? $this->createDefaultView();
        $this->bbs = $bbs ?? SimpleBBS::create($this->storagePath);

        $this->bootstrap();
    }

    public static function create(
        ?string $storagePath = null,
        ?Environment $view = null,
        ?string $viewsPath = null,
        ?SimpleBBS $bbs = null
    ): self {
        $packageRoot = dirname(__DIR__);
        $storagePath ??= $packageRoot . '/.storage';
        $viewsPath ??= $packageRoot . '/resources/views';

        $bbs ??= SimpleBBS::create($storagePath);

        return new self($storagePath, $view, $viewsPath, $bbs);
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function getBbs(): SimpleBBS
    {
        return $this->bbs;
    }

    public function handle(Request $request): void
    {
        $route = (string)$request->query('route', 'boards.index');

        try {
            $response = $this->router->dispatch($route, $request);

            if (is_string($response)) {
                echo $response;
            }
        } catch (\Throwable $exception) {
            http_response_code(500);
            echo 'アプリケーションエラー: ' . htmlspecialchars(
                $exception->getMessage(),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
        }
    }

    private function bootstrap(): void
    {
        $boardController = new BoardController(
            $this->view,
            $this->bbs->boards(),
            $this->bbs->threads()
        );
        $threadController = new ThreadController(
            $this->view,
            $this->bbs->boards(),
            $this->bbs->threads()
        );

        $router = new Router();
        $router->get('boards.index', [$boardController, 'index']);
        $router->post('boards.store', [$boardController, 'store']);
        $router->get('boards.show', [$boardController, 'show']);

        $router->post('threads.store', [$threadController, 'create']);
        $router->get('threads.show', [$threadController, 'show']);
        $router->post('threads.posts.store', [$threadController, 'storePost']);

        $this->router = $router;
    }

    private function createDefaultView(): Environment
    {
        $loader = new FilesystemLoader($this->viewsPath);

        return new Environment($loader, [
            'cache' => false,
        ]);
    }
}

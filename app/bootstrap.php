<?php

use App\Controllers\BoardController;
use App\Controllers\ThreadController;
use App\Core\Router;
use App\Http\Request;
use App\Repositories\BoardRepository;
use App\Repositories\ThreadRepository;
use App\Services\BoardService;
use App\Services\ThreadService;
use App\Support\DatabaseManager;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

autoload();

function autoload(): void
{
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        throw new \RuntimeException('vendor/autoload.php が見つかりません。composer install を実行してください。');
    }

    require_once $autoloadPath;
}

function app(): array
{
    $dataPath = __DIR__ . '/../storage';
    $databaseManager = new DatabaseManager($dataPath);

    $loader = new FilesystemLoader(__DIR__ . '/../resources/views');
    $twig = new Environment($loader, [
        'cache' => false,
    ]);

    $boardRepository = new BoardRepository($databaseManager);
    $threadRepository = new ThreadRepository($databaseManager);

    $boardService = new BoardService($boardRepository);
    $threadService = new ThreadService($threadRepository);

    $boardController = new BoardController($twig, $boardService, $threadService);
    $threadController = new ThreadController($twig, $boardService, $threadService);

    $router = new Router();
    $router->get('boards.index', [$boardController, 'index']);
    $router->post('boards.store', [$boardController, 'store']);
    $router->get('boards.show', [$boardController, 'show']);

    $router->post('threads.store', [$threadController, 'create']);
    $router->get('threads.show', [$threadController, 'show']);
    $router->post('threads.posts.store', [$threadController, 'storePost']);

    return [
        'router' => $router,
    ];
}

function handle(Request $request): void
{
    $container = app();
    /** @var Router $router */
    $router = $container['router'];

    $route = (string)$request->query('route', 'boards.index');

    try {
        $response = $router->dispatch($route, $request);
        if (is_string($response)) {
            echo $response;
        }
    } catch (\Throwable $exception) {
        http_response_code(500);
        echo 'アプリケーションエラー: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

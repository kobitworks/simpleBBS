<?php

namespace SimpleBBS;

use SimpleBBS\Auth\AuthManager;
use SimpleBBS\Auth\AuthenticatorInterface;
use SimpleBBS\Auth\GuestAuthenticator;
use SimpleBBS\Controllers\AuthController;
use SimpleBBS\Controllers\BoardController;
use SimpleBBS\Controllers\ThreadController;
use SimpleBBS\Core\Router;
use SimpleBBS\Http\Request;
use SimpleBBS\Support\Config;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require_once __DIR__ . '/autoload.php';

class Application
{
    private Router $router;
    private Environment $view;
    private string $viewsPath;
    private SimpleBBS $bbs;
    private AuthManager $authManager;
    private Config $config;
    /** @var string[] */
    private array $publicRoutes = [];

    public function __construct(
        private readonly string $storagePath,
        ?Environment $view = null,
        ?string $viewsPath = null,
        ?SimpleBBS $bbs = null,
        ?AuthManager $authManager = null,
        ?Config $config = null
    ) {
        $this->viewsPath = $viewsPath ?? dirname(__DIR__) . '/resources/views';
        $this->view = $view ?? $this->createDefaultView();
        $this->bbs = $bbs ?? SimpleBBS::create($this->storagePath);
        $this->config = $config ?? Config::fromEnvironment();
        $this->authManager = $authManager ?? $this->createDefaultAuthManager();

        if ($this->config->requiresLogin() && !$this->authManager->supportsLoginRedirect()) {
            throw new \RuntimeException('ログイン必須ですが、有効な認証設定が見つかりません。');
        }

        $this->bootstrap();
    }

    public static function create(
        ?string $storagePath = null,
        ?Environment $view = null,
        ?string $viewsPath = null,
        ?SimpleBBS $bbs = null,
        ?AuthenticatorInterface $authenticator = null,
        ?Config $config = null
    ): self {
        $packageRoot = dirname(__DIR__);
        $storagePath ??= $packageRoot . '/.storage';
        $viewsPath ??= $packageRoot . '/resources/views';

        $bbs ??= SimpleBBS::create($storagePath);

        $authManager = null;

        if ($authenticator) {
            $authManager = new AuthManager($authenticator);
        }

        return new self($storagePath, $view, $viewsPath, $bbs, $authManager, $config);
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
        $user = $this->authManager->user($request);
        $this->view->addGlobal('authUser', $user);
        $request->setUser($user);

        if (
            $this->config->requiresLogin()
            && !$user
            && !in_array($route, $this->publicRoutes, true)
        ) {
            header('Location: ?route=auth.login');
            exit;
        }

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
            $this->bbs->threads(),
            $this->config
        );
        $threadController = new ThreadController(
            $this->view,
            $this->bbs->boards(),
            $this->bbs->threads(),
            $this->config
        );
        $authController = new AuthController($this->view, $this->authManager);

        $router = new Router();
        $router->get('auth.login', [$authController, 'login']);
        $this->publicRoutes[] = 'auth.login';

        $router->post('auth.logout', [$authController, 'logout']);

        $router->get('boards.index', [$boardController, 'index']);
        $router->post('boards.store', [$boardController, 'store']);
        $router->get('boards.show', [$boardController, 'show']);

        $router->post('threads.store', [$threadController, 'create']);
        $router->get('threads.show', [$threadController, 'show']);
        $router->post('threads.posts.store', [$threadController, 'storePost']);

        $this->router = $router;

        $this->view->addGlobal('features', [
            'requireLogin' => $this->config->requiresLogin(),
            'allowAnonymousPosting' => $this->config->allowsAnonymousPosting(),
            'allowUserBoardCreation' => $this->config->allowsUserBoardCreation(),
        ]);
        $this->view->addGlobal('authSupportsLogin', $this->authManager->supportsLoginRedirect());
    }

    private function createDefaultView(): Environment
    {
        $loader = new FilesystemLoader($this->viewsPath);

        return new Environment($loader, [
            'cache' => false,
        ]);
    }

    private function createDefaultAuthManager(): AuthManager
    {
        if ($this->config->requiresLogin()) {
            throw new \RuntimeException(
                'ログイン必須の設定ですが、対応する認証方式が提供されていません。'
            );
        }

        return new AuthManager(new GuestAuthenticator());
    }
}

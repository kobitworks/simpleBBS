<?php

namespace SimpleBBS\Controllers;

use SimpleBBS\Auth\AuthManager;
use SimpleBBS\Http\Request;
use SimpleBBS\Services\PasswordAuthService;
use Twig\Environment;

class AuthController
{
    public function __construct(
        private readonly Environment $view,
        private readonly AuthManager $authManager,
        private readonly ?PasswordAuthService $passwordAuth = null
    ) {
    }

    public function login(Request $request): string
    {
        if ($this->authManager->check($request)) {
            header('Location: ?route=boards.index');
            exit;
        }

        return $this->view->render('auth/login.twig', [
            'errors' => [],
            'login' => $this->authManager->loginViewData(),
            'supportsPassword' => $this->authManager->supportsPasswordLogin(),
            'notice' => (string)$request->query('notice', ''),
            'values' => ['email' => (string)$request->query('email', '')],
        ]);
    }

    public function attemptLogin(Request $request): string
    {
        if (!$this->passwordAuth || !$this->authManager->supportsPasswordLogin()) {
            header('Location: ?route=auth.login');
            exit;
        }

        $email = (string)$request->input('email', '');
        $password = (string)$request->input('password', '');
        $errors = [];

        if (!$this->passwordAuth->attemptLogin($email, $password)) {
            $errors[] = 'メールアドレスまたはパスワードが正しくありません。';
        }

        if ($errors === []) {
            header('Location: ?route=boards.index');
            exit;
        }

        return $this->view->render('auth/login.twig', [
            'errors' => $errors,
            'login' => $this->authManager->loginViewData(),
            'supportsPassword' => $this->authManager->supportsPasswordLogin(),
            'notice' => '',
            'values' => ['email' => $email],
        ]);
    }

    public function redirect(Request $request): void
    {
        if (!$this->authManager->supportsLoginRedirect()) {
            header('Location: ?route=boards.index');
            exit;
        }

        $this->authManager->initiateLogin($request);
    }

    public function callback(Request $request): void
    {
        $user = $this->authManager->handleCallback($request);

        if ($user) {
            header('Location: ?route=boards.index');
            exit;
        }

        echo $this->view->render('auth/login.twig', [
            'errors' => ['Googleログインに失敗しました。'],
            'login' => $this->authManager->loginViewData(),
        ]);
    }

    public function logout(Request $request): void
    {
        $this->authManager->logout($request);
        $target = $this->authManager->supportsLogin() ? '?route=auth.login' : '?route=boards.index';
        header('Location: ' . $target);
        exit;
    }

    public function showRegister(Request $request): string
    {
        if (!$this->passwordAuth || !$this->authManager->supportsPasswordLogin()) {
            header('Location: ?route=auth.login');
            exit;
        }

        return $this->view->render('auth/register.twig', [
            'errors' => [],
            'values' => [
                'name' => (string)$request->query('name', ''),
                'email' => (string)$request->query('email', ''),
            ],
        ]);
    }

    public function register(Request $request): string
    {
        if (!$this->passwordAuth || !$this->authManager->supportsPasswordLogin()) {
            header('Location: ?route=auth.login');
            exit;
        }

        $name = trim((string)$request->input('name', ''));
        $email = trim((string)$request->input('email', ''));
        $errors = [];

        if ($name === '') {
            $errors[] = 'お名前を入力してください。';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '有効なメールアドレスを入力してください。';
        }

        if ($errors !== []) {
            return $this->view->render('auth/register.twig', [
                'errors' => $errors,
                'values' => [
                    'name' => $name,
                    'email' => $email,
                ],
            ]);
        }

        $this->passwordAuth->requestPasswordSetup(
            $name,
            $email,
            fn (string $token): string => $this->buildAbsoluteUrl('auth.password.edit', ['token' => $token])
        );

        header('Location: ?route=auth.login&notice=registration_sent&email=' . rawurlencode($email));
        exit;
    }

    public function showPasswordForm(Request $request): string
    {
        if (!$this->passwordAuth || !$this->authManager->supportsPasswordLogin()) {
            header('Location: ?route=auth.login');
            exit;
        }

        $token = (string)$request->query('token', '');
        $data = $this->passwordAuth->findPasswordToken($token);

        if (!$data) {
            return $this->view->render('auth/password_invalid.twig');
        }

        return $this->view->render('auth/password_set.twig', [
            'errors' => [],
            'token' => $data['token'],
            'user' => $data['user'],
        ]);
    }

    public function updatePassword(Request $request): string
    {
        if (!$this->passwordAuth || !$this->authManager->supportsPasswordLogin()) {
            header('Location: ?route=auth.login');
            exit;
        }

        $token = (string)$request->input('token', '');
        $password = (string)$request->input('password', '');
        $confirmation = (string)$request->input('password_confirmation', '');
        $errors = [];
        $data = $this->passwordAuth->findPasswordToken($token);

        if (!$data) {
            return $this->view->render('auth/password_invalid.twig');
        }

        if (mb_strlen($password) < 8) {
            $errors[] = 'パスワードは8文字以上で入力してください。';
        }

        if ($password !== $confirmation) {
            $errors[] = '確認用パスワードが一致しません。';
        }

        if ($errors !== []) {
            return $this->view->render('auth/password_set.twig', [
                'errors' => $errors,
                'token' => $data['token'],
                'user' => $data['user'],
            ]);
        }

        if ($this->passwordAuth->completePasswordSetup($token, $password)) {
            header('Location: ?route=boards.index');
            exit;
        }

        return $this->view->render('auth/password_invalid.twig');
    }

    /**
     * @param array<string, string> $parameters
     */
    private function buildAbsoluteUrl(string $route, array $parameters = []): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? null;
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        if ($host) {
            $base = sprintf('%s://%s%s', $scheme, $host, $script);
        } else {
            $base = $script;
        }

        $params = array_merge(['route' => $route], $parameters);

        return $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($params);
    }
}

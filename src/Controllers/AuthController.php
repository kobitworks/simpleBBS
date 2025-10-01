<?php

namespace SimpleBBS\Controllers;

use SimpleBBS\Auth\AuthManager;
use SimpleBBS\Http\Request;
use Twig\Environment;

class AuthController
{
    public function __construct(
        private readonly Environment $view,
        private readonly AuthManager $authManager
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
        header('Location: ?route=auth.login');
        exit;
    }
}

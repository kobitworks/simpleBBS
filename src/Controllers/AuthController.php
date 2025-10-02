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

    public function logout(Request $request): void
    {
        $this->authManager->logout($request);
        $target = $this->authManager->supportsLoginRedirect() ? '?route=auth.login' : '?route=boards.index';
        header('Location: ' . $target);
        exit;
    }
}

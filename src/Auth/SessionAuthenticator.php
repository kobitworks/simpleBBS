<?php

namespace SimpleBBS\Auth;

use SimpleBBS\Http\Request;
use SimpleBBS\Repositories\UserRepository;

class SessionAuthenticator implements AuthenticatorInterface
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function currentUser(Request $request): ?User
    {
        $this->ensureSession();

        $userId = $_SESSION['simplebbs_user_id'] ?? null;

        if (!$userId) {
            return null;
        }

        $record = $this->users->findById((int)$userId);

        if (!$record) {
            unset($_SESSION['simplebbs_user_id']);

            return null;
        }

        return new User(
            (string)$record['id'],
            (string)$record['name'],
            (string)$record['email']
        );
    }

    public function supportsLoginRedirect(): bool
    {
        return false;
    }

    public function supportsPasswordLogin(): bool
    {
        return true;
    }

    public function initiateLogin(Request $request): void
    {
        // フォームによるログインのため、ここで行う処理はありません。
    }

    public function handleCallback(Request $request): ?User
    {
        return $this->currentUser($request);
    }

    public function logout(Request $request): void
    {
        $this->ensureSession();

        unset($_SESSION['simplebbs_user_id']);
    }

    public function loginViewData(): array
    {
        return [
            'password' => [
                'enabled' => true,
                'action' => '?route=auth.login.attempt',
                'registerRoute' => '?route=auth.register',
            ],
        ];
    }

    public function loginUserId(int $userId): void
    {
        $this->ensureSession();

        $_SESSION['simplebbs_user_id'] = $userId;
    }

    private function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}

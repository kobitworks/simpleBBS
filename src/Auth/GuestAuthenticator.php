<?php

namespace SimpleBBS\Auth;

use SimpleBBS\Http\Request;

class GuestAuthenticator implements AuthenticatorInterface
{
    public function currentUser(Request $request): ?User
    {
        return null;
    }

    public function supportsLoginRedirect(): bool
    {
        return false;
    }

    public function initiateLogin(Request $request): void
    {
        // ゲストモードでは何もしません。
    }

    public function handleCallback(Request $request): ?User
    {
        return null;
    }

    public function logout(Request $request): void
    {
        // ゲストモードでは特に処理はありません。
    }

    public function loginViewData(): array
    {
        return [
            'message' => 'ログイン機能は無効化されています。',
        ];
    }
}

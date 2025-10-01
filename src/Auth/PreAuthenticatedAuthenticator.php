<?php

namespace SimpleBBS\Auth;

use SimpleBBS\Http\Request;

class PreAuthenticatedAuthenticator implements AuthenticatorInterface
{
    public function __construct(private readonly User $user)
    {
    }

    public function currentUser(Request $request): ?User
    {
        return $this->user;
    }

    public function supportsLoginRedirect(): bool
    {
        return false;
    }

    public function initiateLogin(Request $request): void
    {
        // 組み込み利用時は外部システムで認証済みの想定のため特別な処理は不要
    }

    public function handleCallback(Request $request): ?User
    {
        return $this->user;
    }

    public function logout(Request $request): void
    {
        // 外部システムで管理されるため、ここでは何もしない
    }

    public function loginViewData(): array
    {
        return [
            'message' => '外部システムから利用されています。',
        ];
    }
}

<?php

namespace SimpleBBS\Auth;

use SimpleBBS\Http\Request;

interface AuthenticatorInterface
{
    public function currentUser(Request $request): ?User;

    public function supportsLoginRedirect(): bool;

    public function supportsPasswordLogin(): bool;

    public function initiateLogin(Request $request): void;

    public function handleCallback(Request $request): ?User;

    public function logout(Request $request): void;

    /**
     * @return array<string, mixed>
     */
    public function loginViewData(): array;
}

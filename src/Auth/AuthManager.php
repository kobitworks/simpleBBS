<?php

namespace SimpleBBS\Auth;

use SimpleBBS\Http\Request;

class AuthManager
{
    public function __construct(private readonly AuthenticatorInterface $authenticator)
    {
    }

    public function user(Request $request): ?User
    {
        return $this->authenticator->currentUser($request);
    }

    public function check(Request $request): bool
    {
        return $this->user($request) !== null;
    }

    public function supportsLoginRedirect(): bool
    {
        return $this->authenticator->supportsLoginRedirect();
    }

    public function initiateLogin(Request $request): void
    {
        $this->authenticator->initiateLogin($request);
    }

    public function handleCallback(Request $request): ?User
    {
        return $this->authenticator->handleCallback($request);
    }

    public function logout(Request $request): void
    {
        $this->authenticator->logout($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function loginViewData(): array
    {
        return $this->authenticator->loginViewData();
    }
}

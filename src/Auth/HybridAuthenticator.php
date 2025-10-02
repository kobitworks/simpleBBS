<?php

namespace SimpleBBS\Auth;

use SimpleBBS\Http\Request;

class HybridAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private readonly SessionAuthenticator $sessionAuthenticator,
        private readonly ?AuthenticatorInterface $secondary = null
    ) {
    }

    public function currentUser(Request $request): ?User
    {
        $user = $this->sessionAuthenticator->currentUser($request);

        if ($user) {
            return $user;
        }

        if ($this->secondary) {
            return $this->secondary->currentUser($request);
        }

        return null;
    }

    public function supportsLoginRedirect(): bool
    {
        return $this->secondary?->supportsLoginRedirect() ?? false;
    }

    public function supportsPasswordLogin(): bool
    {
        return $this->sessionAuthenticator->supportsPasswordLogin();
    }

    public function initiateLogin(Request $request): void
    {
        if ($this->secondary && $this->secondary->supportsLoginRedirect()) {
            $this->secondary->initiateLogin($request);

            return;
        }

        $this->sessionAuthenticator->initiateLogin($request);
    }

    public function handleCallback(Request $request): ?User
    {
        if ($this->secondary) {
            $user = $this->secondary->handleCallback($request);

            if ($user) {
                return $user;
            }
        }

        return $this->sessionAuthenticator->handleCallback($request);
    }

    public function logout(Request $request): void
    {
        $this->sessionAuthenticator->logout($request);

        if ($this->secondary) {
            $this->secondary->logout($request);
        }
    }

    public function loginViewData(): array
    {
        $data = $this->sessionAuthenticator->loginViewData();

        if ($this->secondary) {
            $data['google'] = $this->secondary->loginViewData();
        }

        return $data;
    }

    public function session(): SessionAuthenticator
    {
        return $this->sessionAuthenticator;
    }
}

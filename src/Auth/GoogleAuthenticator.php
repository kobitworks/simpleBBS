<?php

namespace SimpleBBS\Auth;

use League\OAuth2\Client\Provider\Google;
use SimpleBBS\Http\Request;

class GoogleAuthenticator implements AuthenticatorInterface
{
    private Google $provider;

    public function __construct(string $clientId, string $clientSecret, string $redirectUri)
    {
        $this->provider = new Google([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri' => $redirectUri,
        ]);
    }

    public function currentUser(Request $request): ?User
    {
        $this->ensureSession();

        $data = $_SESSION['simplebbs_user'] ?? null;

        if (!is_array($data)) {
            return null;
        }

        return new User(
            (string)($data['id'] ?? ''),
            (string)($data['name'] ?? ''),
            (string)($data['email'] ?? ''),
            $data['avatar'] ?? null
        );
    }

    public function supportsLoginRedirect(): bool
    {
        return true;
    }

    public function supportsPasswordLogin(): bool
    {
        return false;
    }

    public function initiateLogin(Request $request): void
    {
        $this->ensureSession();

        $authorizationUrl = $this->provider->getAuthorizationUrl([
            'scope' => ['openid', 'profile', 'email'],
        ]);

        $_SESSION['simplebbs_oauth2_state'] = $this->provider->getState();

        header('Location: ' . $authorizationUrl);
        exit;
    }

    public function handleCallback(Request $request): ?User
    {
        $this->ensureSession();

        $expectedState = $_SESSION['simplebbs_oauth2_state'] ?? null;
        $state = $request->query('state');

        if (!$expectedState || $state !== $expectedState) {
            unset($_SESSION['simplebbs_oauth2_state']);

            return null;
        }

        $code = $request->query('code');

        if (!$code) {
            return null;
        }

        try {
            $token = $this->provider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);
            $owner = $this->provider->getResourceOwner($token);

            $user = new User(
                (string)$owner->getId(),
                (string)$owner->getName(),
                (string)$owner->getEmail(),
                $owner->getAvatar()
            );

            $_SESSION['simplebbs_user'] = [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'avatar' => $user->getAvatarUrl(),
            ];

            unset($_SESSION['simplebbs_oauth2_state']);

            return $user;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    public function logout(Request $request): void
    {
        $this->ensureSession();

        unset($_SESSION['simplebbs_user'], $_SESSION['simplebbs_oauth2_state']);
    }

    public function loginViewData(): array
    {
        return [
            'loginRoute' => '?route=auth.redirect',
        ];
    }

    private function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}

<?php

namespace SimpleBBS\Services;

use DateTimeImmutable;
use SimpleBBS\Auth\SessionAuthenticator;
use SimpleBBS\Repositories\UserRepository;
use SimpleBBS\Support\PasswordSetupMailer;

class PasswordAuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly SessionAuthenticator $session,
        private readonly PasswordSetupMailer $mailer
    ) {
    }

    public function attemptLogin(string $email, string $password): bool
    {
        $normalizedEmail = strtolower(trim($email));

        if ($normalizedEmail === '') {
            return false;
        }

        $user = $this->users->findByEmail($normalizedEmail);

        if (!$user) {
            return false;
        }

        $hash = $user['password_hash'] ?? null;

        if (!$hash || !password_verify($password, (string)$hash)) {
            return false;
        }

        $this->session->loginUserId((int)$user['id']);

        return true;
    }

    /**
     * @param callable(string):string $urlGenerator
     */
    public function requestPasswordSetup(string $name, string $email, callable $urlGenerator): void
    {
        $normalizedEmail = strtolower(trim($email));
        $name = trim($name);

        $user = $this->users->findByEmail($normalizedEmail);

        if (!$user) {
            $userId = $this->users->createUser($name, $normalizedEmail);
            $user = $this->users->findById($userId);
        } elseif ($name !== '' && $name !== $user['name']) {
            $this->users->updateUserName((int)$user['id'], $name);
            $user = $this->users->findById((int)$user['id']);
        }

        if (!$user) {
            return;
        }

        $this->users->deletePasswordResetsForUser((int)$user['id']);

        $token = bin2hex(random_bytes(32));
        $expiresAt = (new DateTimeImmutable('+1 day'))->format(DATE_ATOM);
        $this->users->storePasswordResetToken((int)$user['id'], $token, $expiresAt);

        $url = $urlGenerator($token);
        $this->mailer->send($user['name'], $user['email'], $url);
    }

    public function findPasswordToken(string $token): ?array
    {
        $token = trim($token);

        if ($token === '') {
            return null;
        }

        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $this->users->purgeExpiredPasswordResets($now);

        $reset = $this->users->findPasswordResetByToken($token);

        if (!$reset) {
            return null;
        }

        $expiresAt = new DateTimeImmutable((string)$reset['expires_at']);

        if ($expiresAt < new DateTimeImmutable()) {
            $this->users->deletePasswordResetByToken($token);

            return null;
        }

        $user = $this->users->findById((int)$reset['user_id']);

        if (!$user) {
            $this->users->deletePasswordResetByToken($token);

            return null;
        }

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    public function completePasswordSetup(string $token, string $password): bool
    {
        $data = $this->findPasswordToken($token);

        if (!$data) {
            return false;
        }

        $user = $data['user'];
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $this->users->updatePassword((int)$user['id'], $hash);
        $this->users->deletePasswordResetsForUser((int)$user['id']);

        $this->session->loginUserId((int)$user['id']);

        return true;
    }
}

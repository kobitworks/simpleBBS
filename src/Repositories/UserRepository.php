<?php

namespace SimpleBBS\Repositories;

use DateTimeImmutable;
use PDO;
use PDOException;
use SimpleBBS\Support\DatabaseManager;

class UserRepository
{
    public function __construct(private readonly DatabaseManager $database)
    {
    }

    public function findById(int $id): ?array
    {
        $pdo = $this->database->getSystemConnection();
        $statement = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    public function findByEmail(string $email): ?array
    {
        $pdo = $this->database->getSystemConnection();
        $statement = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => strtolower($email)]);
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    public function createUser(string $name, string $email): int
    {
        $pdo = $this->database->getSystemConnection();
        $now = (new DateTimeImmutable())->format(DATE_ATOM);

        try {
            $statement = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, created_at, updated_at) '
                . 'VALUES (:name, :email, NULL, :created_at, :updated_at)'
            );
            $statement->execute([
                'name' => $name,
                'email' => strtolower($email),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return (int)$pdo->lastInsertId();
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $existing = $this->findByEmail($email);
                if ($existing) {
                    return (int)$existing['id'];
                }
            }

            throw $exception;
        }
    }

    public function updateUserName(int $id, string $name): void
    {
        $pdo = $this->database->getSystemConnection();
        $statement = $pdo->prepare(
            'UPDATE users SET name = :name, updated_at = :updated_at WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'name' => $name,
            'updated_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    public function updatePassword(int $id, string $hash): void
    {
        $pdo = $this->database->getSystemConnection();
        $statement = $pdo->prepare(
            'UPDATE users SET password_hash = :hash, password_set_at = :password_set_at, updated_at = :updated_at '
            . 'WHERE id = :id'
        );
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $statement->execute([
            'id' => $id,
            'hash' => $hash,
            'password_set_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function deletePasswordResetsForUser(int $userId): void
    {
        $pdo = $this->database->getSystemConnection();
        $statement = $pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
    }

    public function storePasswordResetToken(int $userId, string $token, string $expiresAt): void
    {
        $pdo = $this->database->getSystemConnection();
        $statement = $pdo->prepare(
            'INSERT INTO password_resets (user_id, token, expires_at, created_at) '
            . 'VALUES (:user_id, :token, :expires_at, :created_at)'
        );
        $statement->execute([
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => $expiresAt,
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    public function findPasswordResetByToken(string $token): ?array
    {
        $pdo = $this->database->getSystemConnection();
        $statement = $pdo->prepare('SELECT * FROM password_resets WHERE token = :token LIMIT 1');
        $statement->execute(['token' => $token]);
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    public function deletePasswordResetByToken(string $token): void
    {
        $pdo = $this->database->getSystemConnection();
        $statement = $pdo->prepare('DELETE FROM password_resets WHERE token = :token');
        $statement->execute(['token' => $token]);
    }

    public function purgeExpiredPasswordResets(string $now): void
    {
        $pdo = $this->database->getSystemConnection();
        $statement = $pdo->prepare('DELETE FROM password_resets WHERE expires_at <= :now');
        $statement->execute(['now' => $now]);
    }
}

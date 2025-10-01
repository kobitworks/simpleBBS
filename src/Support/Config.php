<?php

namespace SimpleBBS\Support;

class Config
{
    private const DEFAULTS = [
        'require_login' => false,
        'allow_anonymous_posting' => true,
        'allow_user_board_creation' => true,
    ];

    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(private array $settings)
    {
        $this->settings = array_merge(self::DEFAULTS, $this->settings);
    }

    public static function load(?string $envPath = null): self
    {
        if ($envPath) {
            self::applyEnvFile($envPath);
        }

        return self::fromEnvironment();
    }

    public static function fromEnvironment(): self
    {
        $settings = [];

        $requireLogin = self::getEnvValue(['MUST_LOGIN', 'SIMPLEBBS_REQUIRE_LOGIN']);
        if ($requireLogin !== null) {
            $settings['require_login'] = self::toBool($requireLogin);
        }

        $allowAnonymous = self::getEnvValue(['ANONYMOUS_POST', 'SIMPLEBBS_ALLOW_ANONYMOUS_POST']);
        if ($allowAnonymous !== null) {
            $settings['allow_anonymous_posting'] = self::toBool($allowAnonymous);
        }

        $allowBoardCreation = self::getEnvValue(['USER_BOARD_CREAT', 'SIMPLEBBS_ALLOW_USER_BOARD_CREATION']);
        if ($allowBoardCreation !== null) {
            $settings['allow_user_board_creation'] = self::toBool($allowBoardCreation);
        }

        $storagePath = getenv('SIMPLEBBS_STORAGE_PATH');
        if ($storagePath !== false) {
            $settings['storage_path'] = $storagePath;
        }

        return new self($settings);
    }

    public function requiresLogin(): bool
    {
        return (bool)$this->settings['require_login'];
    }

    public function allowsAnonymousPosting(): bool
    {
        return (bool)$this->settings['allow_anonymous_posting'];
    }

    public function allowsUserBoardCreation(): bool
    {
        return (bool)$this->settings['allow_user_board_creation'];
    }

    public function storagePath(string $default): string
    {
        return isset($this->settings['storage_path']) && $this->settings['storage_path'] !== ''
            ? (string)$this->settings['storage_path']
            : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->settings;
    }

    private static function getEnvValue(array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = getenv($key);

            if ($value !== false) {
                return $value;
            }
        }

        return null;
    }

    private static function toBool(string $value): bool
    {
        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
    }

    private static function applyEnvFile(string $envPath): void
    {
        if (!is_file($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            if ($value !== '' && ($value[0] === '"' || $value[0] === '\'')) {
                $quote = $value[0];
                if (str_ends_with($value, $quote)) {
                    $value = substr($value, 1, -1);
                }
            }

            putenv(sprintf('%s=%s', $key, $value));
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

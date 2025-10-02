<?php

namespace SimpleBBS\Support;

use DateTimeImmutable;

class PasswordSetupMailer
{
    public function __construct(private readonly string $logFile)
    {
        $directory = dirname($this->logFile);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    public function send(string $name, string $email, string $url): void
    {
        $timestamp = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $content = sprintf(
            "[%s] %s <%s> 宛にパスワード設定リンクを送信しました:\n%s\n\n",
            $timestamp,
            $name === '' ? '(未設定)' : $name,
            $email,
            $url
        );

        file_put_contents($this->logFile, $content, FILE_APPEND);
    }
}

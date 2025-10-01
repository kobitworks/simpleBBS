<?php

namespace SimpleBBS\Http;

class Request
{
    public function __construct(
        private readonly string $method,
        private readonly array $query,
        private readonly array $body
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $query = $_GET;
        $body = $_POST;

        return new self($method, $query, $body);
    }

    public function getMethod(): string
    {
        return strtoupper($this->method);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function all(): array
    {
        return [
            'query' => $this->query,
            'body' => $this->body,
        ];
    }
}

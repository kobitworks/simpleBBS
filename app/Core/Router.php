<?php

namespace App\Core;

use App\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    public function get(string $name, callable $action): void
    {
        $this->routes['GET'][$name] = $action;
    }

    public function post(string $name, callable $action): void
    {
        $this->routes['POST'][$name] = $action;
    }

    public function dispatch(string $name, Request $request): mixed
    {
        $method = strtoupper($request->getMethod());

        if (!isset($this->routes[$method])) {
            throw new InvalidArgumentException(sprintf('未対応のHTTPメソッドです: %s', $method));
        }

        if (!isset($this->routes[$method][$name])) {
            throw new RuntimeException(sprintf('ルートが見つかりません: %s [%s]', $name, $method));
        }

        return ($this->routes[$method][$name])($request);
    }
}

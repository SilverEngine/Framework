<?php
declare(strict_types=1);

namespace Silver\Http;

use Silver\Core\Route;
use Silver\Core\Contracts\Http\RequestInterface;
use Silver\Core\AppInstanceTrait;

class Request implements RequestInterface
{
    use AppInstanceTrait;

    private static array $methods = ['get', 'post', 'put', 'delete', 'patch', 'options'];
    private ?string $uri;

    public function __construct()
    {
        $this->uri = $this->resolveUri();
    }

    private function resolveUri(): string
    {
        if (isset($_GET['uri']) && $_GET['uri'] !== '') {
            return $_GET['uri'];
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        if (($q = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $q);
        }

        if (defined('BASEPATH') && BASEPATH !== '' && str_starts_with($uri, BASEPATH)) {
            $uri = substr($uri, strlen(BASEPATH));
        }

        return '/' . ltrim(rawurldecode($uri), '/');
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function method(): string
    {
        $method = strtolower($this->param('_method', $_SERVER['REQUEST_METHOD'] ?? 'GET'));
        return in_array($method, self::$methods, true) ? $method : 'get';
    }

    public function header(string|false $key = false): array
    {
        $headers = [];

        if (str_contains($_SERVER['SERVER_SOFTWARE'] ?? '', 'Apache') && function_exists('apache_request_headers')) {
            $headers = mapArrayKeys(
                apache_request_headers(),
                fn(string $elem): string => strtoupper(str_replace('-', '_', $elem)),
            );
        } else {
            foreach ($_SERVER as $name => $value) {
                if (str_starts_with($name, 'HTTP_')) {
                    $headers[substr(strtoupper($name), 5)] = $value;
                }
            }
        }

        return ($key !== false && isset($headers[$key])) ? [$key => $headers[$key]] : $headers;
    }

    public function all(): array
    {
        return $_REQUEST;
    }

    public function param(string $name, mixed $default = null): mixed
    {
        return $this->all()[$name] ?? $default;
    }

    public function input(string $name, mixed $default = null): mixed
    {
        return $this->all()[$name] ?? $default;
    }

    public function ajax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    public function segment(int $id): ?string
    {
        $chunks = explode('/', $this->getUri() ?? '');
        return $chunks[$id] ?? null;
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function route(): ?Route
    {
        return Route::find($this->getUri() ?? '/', $this->method());
    }
}

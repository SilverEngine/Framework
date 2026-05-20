<?php
declare(strict_types=1);

namespace Silver\Http;

use Silver\Core\Route;
use Silver\Core\Contracts\Http\RequestInterface;
use Silver\Core\AppInstanceTrait;

class Request implements RequestInterface
{
    use AppInstanceTrait;

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
        return HttpMethod::parse(
            $this->param('_method', $_SERVER['REQUEST_METHOD'] ?? 'GET')
        )->value;
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
        return app(Route::class)
            ->find($this->getUri() ?? '/', $this->method());
    }

    /**
     * Typed access to a single request header. Key is case/format-insensitive
     * ("X-Inertia", "x-inertia", "X_INERTIA" all resolve to HTTP_X_INERTIA).
     */
    public function headerValue(string $key, ?string $default = null): ?string
    {
        $norm = strtoupper(str_replace('-', '_', $key));
        $value = $this->header()[$norm] ?? null;

        return $value !== null ? (string) $value : $default;
    }

    public function hasHeader(string $key): bool
    {
        return $this->headerValue($key) !== null;
    }

    /** A query-string value ($_GET). */
    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * The decoded JSON request body, or a single dot-free key from it.
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        static $decoded = null;

        if ($decoded === null) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);
            $decoded = is_array($decoded) ? $decoded : [];
        }

        if ($key === null) {
            return $decoded;
        }

        return $decoded[$key] ?? $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->input($key);

        return $value === null
            ? $default
            : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    /** True when the client expects/accepts a JSON response. */
    public function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        return $this->ajax()
            || str_contains($accept, 'application/json')
            || $this->hasHeader('X-Inertia');
    }
}

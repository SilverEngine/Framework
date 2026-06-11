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

    /** @var array<string, mixed> instance-scoped input overlay ({@see mergeInput()}) */
    private array $merged = [];

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

        $base = \Silver\Core\RuntimeContext::current()->basePath;
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
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
        return array_replace($_REQUEST, $this->merged);
    }

    /**
     * Merge values into this request instance's input overlay (visible
     * to {@see all()}, {@see input()}, {@see param()}) without mutating
     * the $_REQUEST/$_POST superglobals.
     *
     * @param array<string, mixed> $input
     */
    public function mergeInput(array $input): void
    {
        $this->merged = array_replace($this->merged, $input);
    }

    /** @var array<string, UploadedFile> instance-scoped file overlay ({@see mergeFiles()}) */
    private array $mergedFiles = [];

    /**
     * The uploaded file for $key, or null. Overlay (tests / fakes)
     * wins over $_FILES.
     */
    public function file(string $key): ?UploadedFile
    {
        if (isset($this->mergedFiles[$key])) {
            return $this->mergedFiles[$key];
        }
        $spec = $_FILES[$key] ?? null;
        return is_array($spec) && !is_array($spec['name'] ?? null)
            ? UploadedFile::fromSpec($spec)
            : null;
    }

    /** True when $key holds a successfully uploaded file. */
    public function hasFile(string $key): bool
    {
        return $this->file($key)?->isValid() ?? false;
    }

    /** @return array<string, UploadedFile> every single-file upload on the request */
    public function files(): array
    {
        $out = [];
        foreach (array_keys($_FILES) as $key) {
            $f = $this->file((string) $key);
            if ($f !== null) {
                $out[(string) $key] = $f;
            }
        }
        return [...$out, ...$this->mergedFiles];
    }

    /**
     * Inject pre-built UploadedFile instances (visible to {@see file()},
     * {@see hasFile()}, {@see files()}) without touching $_FILES —
     * the file counterpart of {@see mergeInput()}.
     *
     * @param array<string, UploadedFile> $files
     */
    public function mergeFiles(array $files): void
    {
        $this->mergedFiles = array_replace($this->mergedFiles, $files);
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

    /** True for HTTPS requests, including behind a TLS-terminating proxy. */
    public function isSecure(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            return true;
        }
        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
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

    /** @var array<string, mixed>|null per-instance JSON body cache/overlay */
    private ?array $jsonCache = null;

    /**
     * The decoded JSON request body, or a single dot-free key from it.
     * (Cached per Request instance — a `static` cache here would leak
     * between requests in persistent processes and between tests.)
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->jsonCache === null) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);
            $this->jsonCache = is_array($decoded) ? $decoded : [];
        }

        if ($key === null) {
            return $this->jsonCache;
        }

        return $this->jsonCache[$key] ?? $default;
    }

    /**
     * Inject a fake JSON body (visible to {@see json()}) without touching
     * php://input — the JSON counterpart of {@see mergeInput()}.
     *
     * @param array<string, mixed> $json
     */
    public function mergeJson(array $json): void
    {
        $this->jsonCache = array_replace($this->jsonCache ?? [], $json);
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

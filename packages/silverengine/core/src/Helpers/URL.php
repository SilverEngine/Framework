<?php
declare(strict_types=1);

namespace Silver\Helpers;

use Silver\Crypto\Crypter;
use Silver\Exception\Exception;
use Silver\Http\Request;

class URL
{
    private array $parts = [];

    // ---------- signed URLs ----------------------------------------------
    //
    // The HMAC (Crypter::sign, APP_KEY-derived) covers the app-relative
    // path + the full query string (including 'expires'), so neither
    // params nor lifetime can be tampered with. The signature itself
    // rides as the trailing 'signature' query param in the URL-safe
    // base64 alphabet.

    /**
     * Sign a path (+ optional query). $expiresAt (unix ts) makes the
     * link time-limited; it travels as an 'expires' param under the
     * signature's protection.
     */
    public static function signed(string $path, array $query = [], ?int $expiresAt = null): string
    {
        [$base, $params] = self::splitQuery($path);
        foreach ($query as $k => $v) {
            $params[$k] = $v;
        }
        if ($expiresAt !== null) {
            $params['expires'] = $expiresAt;
        }
        unset($params['signature']);

        $canonical = $base . ($params !== [] ? '?' . http_build_query($params) : '');
        $signature = Crypter::sign($canonical);

        return $canonical . ($params !== [] ? '&' : '?') . 'signature=' . $signature;
    }

    /** Signed URL valid for the next $ttlSeconds. */
    public static function temporarySigned(string $path, int $ttlSeconds, array $query = []): string
    {
        return self::signed($path, $query, time() + $ttlSeconds);
    }

    /**
     * Verify a signed URL (string) or the current request. False on a
     * missing/tampered signature or a past 'expires'.
     */
    public static function hasValidSignature(Request|string $url, ?int $now = null): bool
    {
        if ($url instanceof Request) {
            $params = $_GET;
            unset($params['uri']); // dev-server routing param, not part of the signed URL
            $url = ($url->getUri() ?? '/')
                . ($params !== [] ? '?' . http_build_query($params) : '');
        }

        [$base, $params] = self::splitQuery($url);
        $signature = $params['signature'] ?? null;
        if (!is_string($signature) || $signature === '') {
            return false;
        }
        unset($params['signature']);

        $canonical = $base . ($params !== [] ? '?' . http_build_query($params) : '');
        if (!Crypter::verify($canonical, $signature)) {
            return false;
        }

        if (isset($params['expires']) && (int) $params['expires'] < ($now ?? time())) {
            return false;
        }

        return true;
    }

    /** @return array{0: string, 1: array<string, mixed>} [path-without-query, query params] */
    private static function splitQuery(string $url): array
    {
        $queryString = parse_url($url, PHP_URL_QUERY);
        if ($queryString === null || $queryString === false || $queryString === '') {
            return [$url, []];
        }
        $params = [];
        parse_str((string) $queryString, $params);
        return [(string) strstr($url, '?', true), $params];
    }

    // ---------- fluent URL builder ----------------------------------------

    public function __construct(string $url)
    {
        $this->merge($url);
    }

    public static function make(string $url, array $query = []): static
    {
        $instance = new static($url);
        if ($query) {
            $instance->setPart('query', $query);
        }
        return $instance;
    }

    public function setPart(string $key, mixed $value, bool $override = true): static
    {
        if ($key === 'path') {
            $key = (isset($value[0]) && $value[0] === '/') ? 'absPath' : 'relPath';
        }

        if (!isset($this->parts[$key]) || $override) {
            $this->parts[$key] = $value;
        }

        return $this;
    }

    public function getPath(): string
    {
        $abs = $this->part('absPath', '');
        $rel = $this->part('relPath', '');

        if ($abs !== '' && $rel !== '' && !str_ends_with($abs, '/')) {
            $abs .= '/';
        }

        return $abs . $rel;
    }

    public function part(string $key, mixed $default = null): mixed
    {
        if ($key === 'path') {
            return $this->getPath();
        }

        return $this->parts[$key] ?? $default;
    }

    public function merge(string $url, bool $override = true): static
    {
        foreach (parse_url($url) as $key => $value) {
            $this->setPart($key, $value, $override);
        }

        return $this;
    }

    public function __call(string $method, array $args): mixed
    {
        if (str_starts_with($method, 'set')) {
            $key = lcfirst(substr($method, 3));
            $this->setPart($key, $args[0]);
            return $this;
        }

        if (str_starts_with($method, 'get')) {
            $key = lcfirst(substr($method, 3));
            return $this->part($key);
        }

        throw new Exception("Call undefined method " . static::class . "::" . $method . '()');
    }

    public function __toString(): string
    {
        $url = '';

        if ($scheme = $this->part('scheme')) {
            $url .= $scheme . '://';
        }

        if ($host = $this->part('host')) {
            if ($user = $this->part('user')) {
                $url .= urlencode($user);
                if ($pass = $this->part('pass')) {
                    $url .= ':' . urlencode($pass);
                }
                $url .= '@';
            }
            $url .= urlencode($host);
        }

        if ($path = $this->part('path')) {
            $url .= implode('/', array_map('urlencode', explode('/', $path)));
        }

        if (($query = $this->part('query')) && is_array($query)) {
            $url .= '?' . http_build_query($query);
        }

        if ($fragment = $this->part('fragment')) {
            $url .= '#' . $fragment;
        }

        return $url;
    }
}

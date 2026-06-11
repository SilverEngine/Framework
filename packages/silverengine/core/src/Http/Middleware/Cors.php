<?php
declare(strict_types=1);

namespace Silver\Http\Middleware;

use Closure;
use Silver\Core\Contracts\MiddlewareInterface;
use Silver\Core\Env;
use Silver\Http\Request;
use Silver\Http\Response;

/**
 * Config-driven CORS (config/Cors.php, override per-app):
 *
 *   paths                — request path patterns that get CORS handling
 *                          (fnmatch, e.g. '/api/*'); everything else is
 *                          untouched
 *   allowed_origins      — '*' or exact origins; fnmatch wildcards work
 *                          ('https://*.example.com')
 *   allowed_methods      — '*' echoes the requested method on preflight
 *   allowed_headers      — '*' echoes the requested headers on preflight
 *   exposed_headers      — Access-Control-Expose-Headers on real responses
 *   max_age              — preflight cache seconds (0 = omit)
 *   supports_credentials — adds Allow-Credentials and forces origin echo
 *                          (the spec forbids '*' with credentials)
 *
 * Preflights (OPTIONS + Access-Control-Request-Method) short-circuit
 * with 204 and never reach the router. A disallowed origin simply gets
 * no CORS headers — the browser enforces the block; the server doesn't
 * error.
 */
final class Cors implements MiddlewareInterface
{
    private const array DEFAULTS = [
        'paths'                => [],
        'allowed_origins'      => [],
        'allowed_methods'      => ['*'],
        'allowed_headers'      => ['*'],
        'exposed_headers'      => [],
        'max_age'              => 0,
        'supports_credentials' => false,
    ];

    /** @var array<string, mixed> */
    private readonly array $config;

    /** @param array<string, mixed>|null $config explicit config (tests); null = config/Cors.php */
    public function __construct(?array $config = null)
    {
        $this->config = ($config ?? Env::get('cors', [])) + self::DEFAULTS;
    }

    public function execute(Request $req, Response $res, Closure $next): mixed
    {
        $origin = $req->headerValue('Origin');
        if ($origin === null || !$this->pathMatches((string) $req->getUri())) {
            return $next();
        }

        $allowedOrigin = $this->resolveOrigin($origin);

        if ($this->isPreflight($req)) {
            if ($allowedOrigin !== null) {
                $this->applyOrigin($res, $allowedOrigin);
                $res->setHeader('Access-Control-Allow-Methods', $this->allowMethods($req));
                $res->setHeader('Access-Control-Allow-Headers', $this->allowHeaders($req));
                if ((int) $this->config['max_age'] > 0) {
                    $res->setHeader('Access-Control-Max-Age', (string) (int) $this->config['max_age']);
                }
            }
            $res->setCode(204);
            return null; // short-circuit — preflights never hit the router
        }

        if ($allowedOrigin !== null) {
            $this->applyOrigin($res, $allowedOrigin);
            if ($this->config['exposed_headers'] !== []) {
                $res->setHeader(
                    'Access-Control-Expose-Headers',
                    implode(', ', $this->config['exposed_headers']),
                );
            }
        }

        return $next();
    }

    private function isPreflight(Request $req): bool
    {
        return $req->method() === 'options'
            && $req->headerValue('Access-Control-Request-Method') !== null;
    }

    private function pathMatches(string $uri): bool
    {
        return array_any(
            (array) $this->config['paths'],
            static fn (string $pattern): bool => fnmatch($pattern, $uri),
        );
    }

    /** The Allow-Origin value to emit, or null when the origin isn't allowed. */
    private function resolveOrigin(string $origin): ?string
    {
        $allowed     = (array) $this->config['allowed_origins'];
        $credentials = (bool) $this->config['supports_credentials'];

        if (in_array('*', $allowed, true)) {
            // '*' is invalid alongside credentials — echo the caller instead.
            return $credentials ? $origin : '*';
        }
        $matches = array_any(
            $allowed,
            static fn (string $pattern): bool => $pattern === $origin || fnmatch($pattern, $origin),
        );
        return $matches ? $origin : null;
    }

    private function applyOrigin(Response $res, string $allowedOrigin): void
    {
        $res->setHeader('Access-Control-Allow-Origin', $allowedOrigin);
        if ($allowedOrigin !== '*') {
            // Caches must key on the requesting origin once we echo it.
            $res->setHeader('Vary', 'Origin');
        }
        if ((bool) $this->config['supports_credentials']) {
            $res->setHeader('Access-Control-Allow-Credentials', 'true');
        }
    }

    private function allowMethods(Request $req): string
    {
        $methods = (array) $this->config['allowed_methods'];
        if (in_array('*', $methods, true)) {
            return strtoupper($req->headerValue('Access-Control-Request-Method') ?? 'GET');
        }
        return implode(', ', array_map(strtoupper(...), $methods));
    }

    private function allowHeaders(Request $req): string
    {
        $headers = (array) $this->config['allowed_headers'];
        if (in_array('*', $headers, true)) {
            return $req->headerValue('Access-Control-Request-Headers') ?? '*';
        }
        return implode(', ', $headers);
    }
}

<?php
declare(strict_types=1);

namespace Silver\Http\Middleware;

use Closure;
use Silver\Core\Contracts\MiddlewareInterface;
use Silver\Core\Env;
use Silver\Engine\Ghost\Wisp;
use Silver\Http\Csrf\CsrfTokenMismatchException;
use Silver\Http\Csrf\TokenStore;
use Silver\Http\Request;
use Silver\Http\Response;

/**
 * CSRF protection middleware.
 *
 * Read leg (every request): publishes the session token to:
 *   - Wisp shared props as `csrf_token` (Ghost {{ csrf() }} reads
 *     the same store).
 *   - an XSRF-TOKEN cookie (httpOnly=false) so SPA clients reading
 *     it in JS can attach it back as X-XSRF-TOKEN (Inertia/Axios
 *     convention).
 *
 * Verify leg (state-changing verbs): rejects requests without a valid
 * token by throwing {@see CsrfTokenMismatchException}. Token sources
 * (in order): X-XSRF-TOKEN header → X-CSRF-TOKEN header → `_token`
 * field on the request body.
 *
 * Exempt paths (configured via `config/csrf.php → except`) skip
 * verification — useful for webhooks that authenticate by other means.
 */
final class VerifyCsrfToken implements MiddlewareInterface
{
    private const SAFE_METHODS = ['get', 'head', 'options'];

    /**
     * @param array{
     *     cookie_name?: string,
     *     header_names?: list<string>,
     *     field_name?: string,
     *     except?: list<string>,
     * } $config
     */
    public function __construct(
        private readonly TokenStore $store,
        private array $config = [],
    ) {
        $this->config += $this->defaultsFromEnv();
    }

    public function execute(Request $req, Response $res, Closure $next): mixed
    {
        $token = $this->store->current();
        $this->share($token, $res);

        if (!$this->shouldVerify($req)) {
            return $next();
        }

        $sent = $this->extractToken($req);
        if (!$this->store->verify((string) $sent)) {
            throw new CsrfTokenMismatchException();
        }

        return $next();
    }

    private function shouldVerify(Request $req): bool
    {
        if (in_array(strtolower($req->method()), self::SAFE_METHODS, true)) {
            return false;
        }
        $uri = $req->getUri() ?? '/';
        foreach ($this->config['except'] ?? [] as $pattern) {
            if (fnmatch((string) $pattern, $uri)) {
                return false;
            }
        }
        return true;
    }

    private function extractToken(Request $req): ?string
    {
        foreach ($this->config['header_names'] ?? [] as $header) {
            $value = $req->headerValue((string) $header);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        $field = (string) ($this->config['field_name'] ?? '_token');
        $value = $req->input($field);
        return is_string($value) ? $value : null;
    }

    private function share(string $token, Response $res): void
    {
        Wisp::share('csrf_token', $token);

        $cookie = (string) ($this->config['cookie_name'] ?? 'XSRF-TOKEN');
        $secure = !(bool) (Env::get('app.debug') ?? Env::get('debug') ?? false);

        // httpOnly false on purpose — the JS client (Inertia/Axios) reads
        // this cookie and echoes it back as X-XSRF-TOKEN.
        $res->setCookie(
            $cookie,
            $token,
            time() + 7200,
            ['path' => '/', 'samesite' => 'Lax', 'secure' => $secure, 'httponly' => false],
        );
    }

    /** @return array<string, mixed> */
    private function defaultsFromEnv(): array
    {
        return [
            'cookie_name'  => Env::get('csrf.cookie_name', 'XSRF-TOKEN'),
            'header_names' => Env::get('csrf.header_names', ['X-XSRF-TOKEN', 'X-CSRF-TOKEN']),
            'field_name'   => Env::get('csrf.field_name', '_token'),
            'except'       => Env::get('csrf.except', []),
        ];
    }
}

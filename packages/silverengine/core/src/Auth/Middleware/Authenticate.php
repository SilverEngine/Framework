<?php
declare(strict_types=1);

namespace Silver\Auth\Middleware;

use Closure;
use Silver\Auth\AuthenticationException;
use Silver\Auth\AuthManager;
use Silver\Core\Contracts\MiddlewareInterface;
use Silver\Core\Env;
use Silver\Http\Request;
use Silver\Http\Response;
use Silver\Http\Session;

/**
 * Requires an authenticated session. Throws AuthenticationException
 * on miss; the error-handler middleware turns that into either:
 *   - JSON 401 envelope (wantsJson / X-Inertia)
 *   - HTTP 302 → config('auth.login_url') with the intended URL
 *     flashed so the post-login redirect can resume it.
 */
final class Authenticate implements MiddlewareInterface
{
    public function __construct(private readonly AuthManager $auth) {}

    public function execute(Request $req, Response $res, Closure $next): mixed
    {
        if ($this->auth->guard()->check()) {
            return $next();
        }

        $intended = $req->getUri() ?? '/';
        Session::flash('_intended_url', $intended);

        $loginUrl = (string) Env::get('auth.login_url', '/login');
        throw new AuthenticationException('Unauthenticated.', $loginUrl);
    }
}

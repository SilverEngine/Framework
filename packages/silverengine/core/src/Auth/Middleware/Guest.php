<?php
declare(strict_types=1);

namespace Silver\Auth\Middleware;

use Closure;
use Silver\Auth\AuthManager;
use Silver\Core\Contracts\MiddlewareInterface;
use Silver\Core\Env;
use Silver\Http\Request;
use Silver\Http\Response;

/**
 * Inverse of Authenticate — used to keep already-authenticated users
 * off the login / register pages by redirecting them home.
 */
final class Guest implements MiddlewareInterface
{
    public function __construct(private readonly AuthManager $auth) {}

    public function execute(Request $req, Response $res, Closure $next): mixed
    {
        if (!$this->auth->guard()->check()) {
            return $next();
        }

        $home = (string) Env::get('auth.home_url', '/');
        $res->setCode(302);
        $res->setHeader('Location', $home);
        return '';
    }
}

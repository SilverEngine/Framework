<?php
declare(strict_types=1);

namespace Silver\Auth\Middleware;

use Closure;
use Silver\Auth\AuthManager;
use Silver\Auth\Contracts\MustVerifyEmail;
use Silver\Core\Contracts\MiddlewareInterface;
use Silver\Http\AuthorizationException;
use Silver\Http\Request;
use Silver\Http\Response;

/**
 * Route middleware ('verified'): the authenticated user must have a
 * verified email. Models not implementing MustVerifyEmail pass —
 * verification simply doesn't apply to them.
 */
final class EnsureEmailIsVerified implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthManager $auth,
    ) {}

    public function execute(Request $req, Response $res, Closure $next): mixed
    {
        $user = $this->auth->guard()->user();

        if ($user instanceof MustVerifyEmail && !$user->hasVerifiedEmail()) {
            throw new AuthorizationException('Your email address is not verified.');
        }

        return $next();
    }
}

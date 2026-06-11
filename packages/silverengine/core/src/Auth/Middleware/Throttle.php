<?php
declare(strict_types=1);

namespace Silver\Auth\Middleware;

use Closure;
use Silver\Auth\ThrottleRequestsException;
use Silver\Core\Contracts\MiddlewareInterface;
use Silver\Core\Env;
use Silver\Http\Request;
use Silver\Http\Response;
use Silver\Support\RateLimiter;

/**
 * Request throttle on the shared {@see RateLimiter} (sliding window,
 * APCu-backed when enabled — so it works behind php-fpm, unlike the
 * old per-process $_SESSION counter).
 *
 * Keyed by ip + email so two users behind one NAT don't lock each
 * other out, and one attacker on N IPs can't dodge the limit on a
 * single email.
 *
 * Route-middleware friendly: `'throttle:60,1'` maps 60 → maxAttempts,
 * 1 → decaySeconds via MiddlewareResolver; the RateLimiter service
 * dep autowires. Zero/omitted params fall back to auth.throttle.*
 * config. Over the limit throws ThrottleRequestsException (429) with
 * the remaining window seconds in Retry-After.
 */
final class Throttle implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly int $maxAttempts = 0,
        private readonly int $decaySeconds = 0,
    ) {}

    public function execute(Request $req, Response $res, Closure $next): mixed
    {
        $max   = $this->maxAttempts > 0 ? $this->maxAttempts : (int) Env::get('auth.throttle.max', 5);
        $decay = $this->decaySeconds > 0 ? $this->decaySeconds : (int) Env::get('auth.throttle.decay', 60);

        $key = $this->key($req);

        if (!$this->limiter->attempt($key, $max, $decay)) {
            $retry = max(1, $this->limiter->availableIn($key, $decay));
            $res->setHeader('Retry-After', (string) $retry);
            throw new ThrottleRequestsException($retry);
        }

        $res->setHeader('X-RateLimit-Limit', (string) $max);
        $res->setHeader('X-RateLimit-Remaining', (string) $this->limiter->remaining($key, $max, $decay));

        return $next();
    }

    private function key(Request $req): string
    {
        return sha1($req->ip() . '|' . (string) $req->input('email', ''));
    }
}

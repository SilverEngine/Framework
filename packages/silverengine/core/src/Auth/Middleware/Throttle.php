<?php
declare(strict_types=1);

namespace Silver\Auth\Middleware;

use Closure;
use Silver\Auth\ThrottleRequestsException;
use Silver\Core\Contracts\MiddlewareInterface;
use Silver\Core\Env;
use Silver\Http\Request;
use Silver\Http\Response;

/**
 * In-memory login throttle.
 *
 * Counter lives in $_SESSION (no generic Cache backend yet — Branch B
 * will swap this for a Cache::store('rate-limiter') call). Keyed by
 * ip + email so two users behind one NAT don't lock each other out,
 * and one attacker on N IPs can't dodge the limit on a single email.
 *
 * Reaching the limit throws ThrottleRequestsException (429) with the
 * remaining-seconds in Retry-After.
 */
final class Throttle implements MiddlewareInterface
{
    private const SESSION_KEY = '_auth_throttle';

    public function __construct(
        private readonly int $maxAttempts = 0,
        private readonly int $decaySeconds = 0,
    ) {}

    public function execute(Request $req, Response $res, Closure $next): mixed
    {
        $max   = $this->maxAttempts > 0 ? $this->maxAttempts : (int) Env::get('auth.throttle.max', 5);
        $decay = $this->decaySeconds > 0 ? $this->decaySeconds : (int) Env::get('auth.throttle.decay', 60);

        $key   = $this->key($req);
        $now   = time();
        $state = $_SESSION[self::SESSION_KEY][$key] ?? ['hits' => 0, 'reset' => $now + $decay];

        if ($state['reset'] <= $now) {
            $state = ['hits' => 0, 'reset' => $now + $decay];
        }

        if ($state['hits'] >= $max) {
            $retry = max(1, $state['reset'] - $now);
            $res->setHeader('Retry-After', (string) $retry);
            throw new ThrottleRequestsException($retry);
        }

        $state['hits']++;
        $_SESSION[self::SESSION_KEY][$key] = $state;

        return $next();
    }

    private function key(Request $req): string
    {
        return sha1($req->ip() . '|' . (string) $req->input('email', ''));
    }
}

<?php
declare(strict_types=1);

namespace Silver\Auth;

use RuntimeException;
use Throwable;

/**
 * Thrown by {@see Middleware\Authenticate} when a request reaches an
 * `auth`-guarded route without a valid session. JSON clients get a
 * 401 envelope; HTML clients get a redirect to the configured
 * login URL.
 */
final class AuthenticationException extends RuntimeException
{
    public function __construct(
        string $message = 'Unauthenticated.',
        public readonly ?string $redirectTo = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 401, $previous);
    }
}

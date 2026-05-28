<?php
declare(strict_types=1);

namespace Silver\Auth;

use RuntimeException;
use Throwable;

final class ThrottleRequestsException extends RuntimeException
{
    public function __construct(
        public readonly int $retryAfter,
        string $message = 'Too many requests.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 429, $previous);
    }
}

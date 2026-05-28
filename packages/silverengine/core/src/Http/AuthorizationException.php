<?php
declare(strict_types=1);

namespace Silver\Http;

use RuntimeException;
use Throwable;

/**
 * Thrown by {@see FormRequest::authorize()} returning false, or by any
 * gate/policy check that wants the framework to produce a 403 response.
 * Content-negotiated like {@see ValidationException}.
 */
final class AuthorizationException extends RuntimeException
{
    public function __construct(
        string $message = 'This action is unauthorized.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 403, $previous);
    }
}

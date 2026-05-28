<?php
declare(strict_types=1);

namespace Silver\Http\Csrf;

use RuntimeException;
use Throwable;

/**
 * Thrown by {@see \Silver\Http\Middleware\VerifyCsrfToken} when an
 * unsafe request arrives without a valid CSRF token. The error-handler
 * middleware renders 419 (HTML) or JSON envelope (wantsJson).
 */
final class CsrfTokenMismatchException extends RuntimeException
{
    public function __construct(string $message = 'CSRF token mismatch.', ?Throwable $previous = null)
    {
        parent::__construct($message, 419, $previous);
    }
}

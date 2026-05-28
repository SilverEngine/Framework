<?php
declare(strict_types=1);

namespace Silver\Http;

use RuntimeException;
use Throwable;

/**
 * Thrown by {@see FormRequest::validateResolved()} (or any other
 * {@see Contracts\ValidatesData} implementor) when validation fails.
 *
 * The middleware pipeline turns this into a content-negotiated response:
 * JSON 422 envelope for AJAX / Wisp / `Accept: application/json` clients,
 * flash-and-redirect for classic HTML form posts.
 *
 * @phpstan-type ErrorMap array<string, list<string>>
 */
final class ValidationException extends RuntimeException
{
    /** @param ErrorMap $errors */
    public function __construct(
        public readonly array $errors,
        public readonly ?array $oldInput = null,
        string $message = 'The given data was invalid.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 422, $previous);
    }

    /** @return ErrorMap */
    public function errors(): array
    {
        return $this->errors;
    }
}

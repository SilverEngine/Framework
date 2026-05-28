<?php
declare(strict_types=1);

namespace Silver\Orm\Query\Node;

/**
 * Verbatim SQL fragment — emitted unchanged. Trust boundary: the caller
 * is responsible for safety. Use {@see Binding} for any value that
 * originates outside the application.
 *
 * Optional bindings are appended when the raw fragment contains '?'
 * placeholders, so `Raw::of('LOWER(?)', [$email])` interpolates
 * cleanly without leaking parameters into the grammar's compile order.
 */
final readonly class Raw implements Node
{
    /**
     * @param array<int, mixed> $bindings
     */
    public function __construct(
        public string $sql,
        public array  $bindings = [],
    ) {}
}

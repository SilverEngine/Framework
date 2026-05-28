<?php
declare(strict_types=1);

namespace Silver\Orm\Query\Node;

/**
 * A column, table, or aliased reference. Dotted names ("users.email")
 * split on the dot and each segment is quoted independently. A '*' or
 * 'table.*' segment is left unquoted.
 */
final readonly class Identifier implements Node
{
    public function __construct(
        public string $name,
        public ?string $alias = null,
    ) {}

    public static function ensure(string|self $value): self
    {
        return $value instanceof self ? $value : new self($value);
    }
}

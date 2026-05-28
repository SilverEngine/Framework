<?php
declare(strict_types=1);

namespace Silver\Orm\Attributes;

use Attribute;

/**
 * Declare an explicit cast for a property. Accepts:
 *
 *   - a built-in shorthand string: 'json', 'array', 'datetime', 'date',
 *     'bool', 'int', 'float', 'string', 'encrypted'
 *   - a class FQN: any BackedEnum or any class implementing
 *     {@see \Silver\Orm\Casts\CastsAttribute}
 *
 * Native PHP types on the property (?DateTimeImmutable, int, an enum
 * type-hint) are inferred without #[Cast]. Explicit #[Cast] wins on
 * conflict.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Cast
{
    public function __construct(
        public string $type,
        /** @var array<int, mixed> Extra args passed to the cast constructor. */
        public array  $args = [],
    ) {}
}

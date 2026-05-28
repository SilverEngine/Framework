<?php
declare(strict_types=1);

namespace Silver\Orm\Query\Node;

/**
 * A parameterised value that the grammar emits as '?' and appends to
 * the bindings list. The only safe channel for user-supplied values.
 */
final readonly class Binding implements Node
{
    public function __construct(
        public mixed $value,
    ) {}
}

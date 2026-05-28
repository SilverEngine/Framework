<?php
declare(strict_types=1);

namespace Silver\Orm\Query\Node;

use Silver\Orm\Query\QueryState;

/**
 * A nested SELECT used inline (in WHERE EXISTS, in a derived FROM
 * clause, as a scalar projection). The grammar recurses through
 * compileSelect() and inherits its bindings into the outer query.
 */
final readonly class Subquery implements Node
{
    public function __construct(
        public QueryState $state,
        public ?string    $alias = null,
    ) {}
}

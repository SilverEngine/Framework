<?php
declare(strict_types=1);

namespace Silver\Orm\Query\Node;

/**
 * Operator-applied tree. The op determines the arity and shape the
 * grammar expects:
 *
 *   '='|'<>'|'<'|'<='|'>'|'>='|'LIKE'|'NOT LIKE'  → [Node $lhs, Node $rhs]
 *   'AND'|'OR'                                   → list<Node> ≥ 2
 *   'NOT'                                        → [Node]
 *   'IN'|'NOT IN'                                → [Node $lhs, list<Node>]
 *   'BETWEEN'|'NOT BETWEEN'                      → [Node $lhs, Node $low, Node $high]
 *   'IS NULL'|'IS NOT NULL'                      → [Node $lhs]
 *   'EXISTS'|'NOT EXISTS'                        → [Subquery]
 *
 * Grammar performs the dispatch. Keeping the shape rules here as
 * documentation means changing one node, not three.
 */
final readonly class Expression implements Node
{
    /**
     * @param non-empty-string $op
     * @param list<mixed>      $operands
     */
    public function __construct(
        public string $op,
        public array  $operands,
    ) {}
}

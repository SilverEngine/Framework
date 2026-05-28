<?php
declare(strict_types=1);

namespace Silver\Orm\Query\Node;

/**
 * One row in QueryState::$joins. Supports joining a plain table name,
 * an aliased table, or a Subquery (derived table). The ON clause is
 * any compilable Node — typically an Expression or an AND of them.
 */
final readonly class Join implements Node
{
    public const KIND_INNER = 'INNER';
    public const KIND_LEFT  = 'LEFT';
    public const KIND_RIGHT = 'RIGHT';
    public const KIND_CROSS = 'CROSS';

    public function __construct(
        public string                $kind,
        public Identifier|Subquery   $table,
        public ?Node                 $on,
    ) {}
}

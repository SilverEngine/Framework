<?php
declare(strict_types=1);

namespace Silver\Orm\Query;

use Silver\Orm\Query\Node\Identifier;
use Silver\Orm\Query\Node\Join;
use Silver\Orm\Query\Node\Node;
use Silver\Orm\Query\Node\Subquery;

/**
 * Mutable carrier for everything the Builder has accumulated. The
 * Grammar reads this; nothing in the Grammar layer writes to it. Kept
 * as public properties (not getters) so the Grammar can pattern-match
 * directly without boilerplate.
 */
final class QueryState
{
    public Identifier|Subquery|null $from = null;

    /** @var list<Node> */
    public array $select = [];

    public bool $distinct = false;

    /** @var list<Join> */
    public array $joins = [];

    /** @var list<Node> Each entry is combined with the next via AND. */
    public array $wheres = [];

    /** @var list<Node> */
    public array $groups = [];

    /** @var list<Node> */
    public array $havings = [];

    /** @var list<array{0: Node, 1: 'ASC'|'DESC'}> */
    public array $orders = [];

    public ?int $limit  = null;
    public ?int $offset = null;

    /** @var list<array{0: QueryState, 1: bool}> [state, all] */
    public array $unions = [];

    public ?string $lock = null;

    public ?string $connection = null;
}

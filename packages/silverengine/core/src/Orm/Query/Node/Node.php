<?php
declare(strict_types=1);

namespace Silver\Orm\Query\Node;

/**
 * Marker for anything a Grammar can compile. The grammar branches on
 * concrete class, so the interface is intentionally empty — adding
 * methods here would push driver-specific concerns into the nodes.
 */
interface Node
{
}

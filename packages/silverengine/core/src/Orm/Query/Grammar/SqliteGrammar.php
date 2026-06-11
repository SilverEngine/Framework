<?php
declare(strict_types=1);

namespace Silver\Orm\Query\Grammar;

use Silver\Orm\Connection\Driver;
use Silver\Orm\Query\QueryState;

final class SqliteGrammar extends Grammar
{
    public function driver(): Driver { return Driver::Sqlite; }

    /**
     * Sqlite has no EXPLAIN ANALYZE — `EXPLAIN QUERY PLAN` is the
     * closest thing and is read-only. The Builder distinguishes
     * `explain()` and `analyze()` on its own, executing the real query
     * for `analyze()` and timing it via the QueryExecuted event.
     */
    #[\Override]
    public function explainPrefix(): string { return 'EXPLAIN QUERY PLAN '; }
    #[\Override]
    public function analyzePrefix(): string { return 'EXPLAIN QUERY PLAN '; }

    /**
     * Sqlite locks the whole database file — FOR UPDATE / FOR SHARE
     * don't exist and would be syntax errors. Emit nothing.
     */
    #[\Override]
    protected function compileLock(QueryState $state): string
    {
        return '';
    }

    /**
     * Sqlite has no JSON_CONTAINS — emulate per element via json_each:
     * EXISTS (SELECT 1 FROM json_each(col) WHERE json_each.value = ?).
     * An array candidate means "contains all", ANDing one EXISTS per
     * element (matching the containment semantics of mysql/pgsql).
     */
    #[\Override]
    protected function compileJsonContains(\Silver\Orm\Query\Node\Expression $e, array &$bindings): string
    {
        [$column, $value] = $e->operands;
        $col      = $this->compileNode($column, $bindings);
        $elements = is_array($value->value) ? array_values($value->value) : [$value->value];

        $parts = [];
        foreach ($elements as $el) {
            $parts[]    = 'EXISTS (SELECT 1 FROM json_each(' . $col . ') WHERE json_each.value = ?)';
            $bindings[] = $el;
        }
        return count($parts) === 1 ? $parts[0] : '(' . implode(' AND ', $parts) . ')';
    }
}

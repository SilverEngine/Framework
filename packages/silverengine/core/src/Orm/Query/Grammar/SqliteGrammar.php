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
}

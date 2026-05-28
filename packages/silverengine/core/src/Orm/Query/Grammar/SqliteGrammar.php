<?php
declare(strict_types=1);

namespace Silver\Orm\Query\Grammar;

use Silver\Orm\Connection\Driver;

final class SqliteGrammar extends Grammar
{
    public function driver(): Driver { return Driver::Sqlite; }

    /**
     * Sqlite has no EXPLAIN ANALYZE — `EXPLAIN QUERY PLAN` is the
     * closest thing and is read-only. The Builder distinguishes
     * `explain()` and `analyze()` on its own, executing the real query
     * for `analyze()` and timing it via the QueryExecuted event.
     */
    public function explainPrefix(): string { return 'EXPLAIN QUERY PLAN '; }
    public function analyzePrefix(): string { return 'EXPLAIN QUERY PLAN '; }
}

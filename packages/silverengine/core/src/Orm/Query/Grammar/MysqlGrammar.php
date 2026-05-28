<?php
declare(strict_types=1);

namespace Silver\Orm\Query\Grammar;

use Silver\Orm\Connection\Driver;

/**
 * MySQL 8+ query compiler. Identifier quoting (backticks) is handled
 * by the Driver enum; everything inherited from Grammar emits ANSI
 * SQL that MySQL accepts as-is, including subqueries, joins,
 * GROUP BY, HAVING, UNION, and LIMIT/OFFSET.
 *
 * EXPLAIN: FORMAT=TREE since MySQL 8.0.18.
 * EXPLAIN ANALYZE since MySQL 8.0.18 — wraps the SELECT and runs it.
 */
final class MysqlGrammar extends Grammar
{
    public function driver(): Driver { return Driver::Mysql; }

    #[\Override]
    public function explainPrefix(): string { return 'EXPLAIN FORMAT=TREE '; }
    #[\Override]
    public function analyzePrefix(): string { return 'EXPLAIN ANALYZE FORMAT=TREE '; }
}

<?php
declare(strict_types=1);

namespace Silver\Orm\Query\Grammar;

use Silver\Orm\Connection\Driver;
use Silver\Orm\Query\QueryState;

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

    /** MySQL has no ON CONFLICT clause — INSERT IGNORE is the idiom. */
    #[\Override]
    public function compileInsertOrIgnore(string $table, array $rows): array
    {
        [$sql, $bindings] = $this->compileInsert($table, $rows);
        return [(string) preg_replace('/^INSERT INTO/', 'INSERT IGNORE INTO', $sql), $bindings];
    }

    /**
     * MySQL upsert: ON DUPLICATE KEY UPDATE. The conflict target is
     * implicit (any unique key), so $uniqueBy is unused here — it is
     * still validated for portability with the ANSI grammars.
     */
    #[\Override]
    public function compileUpsert(string $table, array $rows, array $uniqueBy, array $updateColumns): array
    {
        if ($uniqueBy === [] || $updateColumns === []) {
            throw new \InvalidArgumentException('Upsert requires uniqueBy and update columns.');
        }

        [$sql, $bindings] = $this->compileInsert($table, $rows);

        $sets = [];
        foreach ($updateColumns as $col) {
            $q      = $this->quote($col);
            $sets[] = "{$q} = VALUES({$q})";
        }

        return [$sql . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $sets), $bindings];
    }

    /** Pre-8.0 / MariaDB-portable shared lock spelling. */
    #[\Override]
    protected function compileLock(QueryState $state): string
    {
        return $state->lock === 'share' ? 'LOCK IN SHARE MODE' : parent::compileLock($state);
    }
}

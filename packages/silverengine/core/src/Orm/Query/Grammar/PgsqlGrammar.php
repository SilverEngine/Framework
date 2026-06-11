<?php
declare(strict_types=1);

namespace Silver\Orm\Query\Grammar;

use Silver\Orm\Connection\Driver;

/**
 * PostgreSQL query compiler. Double-quote identifier quoting comes
 * from the Driver enum. The ANSI core inherited from Grammar covers
 * everything we emit; postgres-specific bells (RETURNING, ON
 * CONFLICT, ILIKE) are an opt-in extension point for later, not
 * needed for the v1 feature set.
 */
final class PgsqlGrammar extends Grammar
{
    public function driver(): Driver { return Driver::Pgsql; }

    #[\Override]
    public function explainPrefix(): string { return 'EXPLAIN (FORMAT JSON) '; }
    #[\Override]
    public function analyzePrefix(): string { return 'EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) '; }

    /** jsonb containment: col::jsonb @> ? with a JSON-encoded candidate. */
    #[\Override]
    protected function compileJsonContains(\Silver\Orm\Query\Node\Expression $e, array &$bindings): string
    {
        [$column, $value] = $e->operands;
        $sql = $this->compileNode($column, $bindings) . '::jsonb @> ?';
        $bindings[] = json_encode($value->value, JSON_THROW_ON_ERROR);
        return $sql;
    }
}

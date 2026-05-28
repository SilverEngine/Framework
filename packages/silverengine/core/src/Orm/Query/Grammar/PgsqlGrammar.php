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
}

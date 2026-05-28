<?php
declare(strict_types=1);

namespace Silver\Orm\Query;

use Silver\Orm\Connection\Driver;
use Stringable;

/**
 * Immutable result of {@see Builder::explain()} / {@see Builder::analyze()}.
 *
 * `$rows` is the raw driver-shaped output; `$formatted` is a tidy
 * ASCII layout safe to echo to a terminal or write into a `/debug`
 * page. `$totalMs` is null for explain() (no execution timing) and
 * set for analyze().
 */
final readonly class QueryPlan implements Stringable
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(
        public string $sql,            // the EXPLAIN-wrapped SQL actually run
        public string $originalSql,    // the underlying SELECT being analysed
        /** @var list<mixed> */
        public array  $bindings,
        public Driver $driver,
        public array  $rows,
        public ?float $totalMs,
        public string $formatted,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'sql'         => $this->sql,
            'originalSql' => $this->originalSql,
            'bindings'    => $this->bindings,
            'driver'      => $this->driver->value,
            'rows'        => $this->rows,
            'totalMs'     => $this->totalMs,
            'formatted'   => $this->formatted,
        ];
    }

    public function __toString(): string { return $this->formatted; }
}

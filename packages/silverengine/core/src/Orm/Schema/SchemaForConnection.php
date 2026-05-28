<?php
declare(strict_types=1);

namespace Silver\Orm\Schema;

use Closure;
use Silver\Orm\Connection\ConnectionManager;

/**
 * Connection-scoped Schema proxy returned by Schema::connection().
 * Methods mirror the static Schema API but route every call through
 * a temporary connection switch — no global mutation, no leakage if
 * the migration throws.
 */
final readonly class SchemaForConnection
{
    public function __construct(
        private ConnectionManager $cm,
        private string            $connection,
    ) {}

    public function create(string $table, Closure $cb): void
    {
        $this->cm->withConnection($this->connection, fn (): mixed => $this->scoped(fn () => Schema::create($table, $cb)));
    }

    public function table(string $table, Closure $cb): void
    {
        $this->cm->withConnection($this->connection, fn (): mixed => $this->scoped(fn () => Schema::table($table, $cb)));
    }

    public function drop(string $table): void
    {
        $this->cm->withConnection($this->connection, fn (): mixed => $this->scoped(fn () => Schema::drop($table)));
    }

    public function dropIfExists(string $table): void
    {
        $this->cm->withConnection($this->connection, fn (): mixed => $this->scoped(fn () => Schema::dropIfExists($table)));
    }

    public function rename(string $from, string $to): void
    {
        $this->cm->withConnection($this->connection, fn (): mixed => $this->scoped(fn () => Schema::rename($from, $to)));
    }

    public function hasTable(string $table): bool
    {
        return $this->cm->withConnection(
            $this->connection,
            fn (): mixed => $this->scoped(fn (): bool => Schema::hasTable($table)),
        );
    }

    public function hasColumn(string $table, string $column): bool
    {
        return $this->cm->withConnection(
            $this->connection,
            fn (): mixed => $this->scoped(fn (): bool => Schema::hasColumn($table, $column)),
        );
    }

    /** Push Schema's "current connection" hint for the call, then restore. */
    private function scoped(Closure $cb): mixed
    {
        $prev = Schema::currentConnection();
        Schema::useConnection($this->connection);
        try {
            return $cb();
        } finally {
            Schema::useConnection($prev);
        }
    }
}

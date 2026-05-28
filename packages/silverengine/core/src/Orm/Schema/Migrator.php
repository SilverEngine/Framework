<?php
declare(strict_types=1);

namespace Silver\Orm\Schema;

use Silver\Orm\Connection\ConnectionConfig;
use PDO;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Connection\TransactionManager;
use Throwable;

/**
 * Per-connection migration engine. Each connection has:
 *
 *   - a directory of migration files (set via ConnectionConfig::$migrationsPath)
 *   - a tracking table in that connection's database (ConnectionConfig::$migrationsTable, default 'migrations')
 *     with columns (migration TEXT PK, batch INTEGER, ran_at TEXT).
 *
 * Multi-connection callers loop over connections and instantiate one
 * Migrator per name.
 */
final readonly class Migrator
{
    public function __construct(
        private ConnectionManager  $connections,
        private TransactionManager $tx,
        private string             $connection,
    ) {
        Schema::bind($this->connections);
    }

    /**
     * Apply all pending migrations as one logical batch.
     *
     * @return list<MigrationRun>
     */
    public function run(bool $pretend = false): array
    {
        $this->ensureTrackingTable();

        $pending = $this->pending();
        if ($pending === []) {
            return [];
        }

        $batch = $this->nextBatch();
        $results = [];

        foreach ($pending as $entry) {
            $results[] = $this->apply($entry, $batch, $pretend);
        }
        return $results;
    }

    /**
     * Roll back the most recent batch, or the last $steps batches.
     *
     * @return list<MigrationRun>
     */
    public function rollback(int $steps = 1, bool $pretend = false): array
    {
        $this->ensureTrackingTable();

        $batches = $this->batchesDescending($steps);
        if ($batches === []) {
            return [];
        }

        $results = [];
        foreach ($batches as $batch) {
            foreach ($this->migrationsInBatch($batch) as $name) {
                $results[] = $this->revert($name, $pretend);
            }
        }
        return $results;
    }

    /** Roll back everything that has run on this connection. */
    public function reset(bool $pretend = false): array
    {
        return $this->rollback(PHP_INT_MAX, $pretend);
    }

    /** Drop every table on this connection, then run() from scratch. */
    public function fresh(): array
    {
        $this->dropAllTables();
        return $this->run();
    }

    /** @return list<MigrationStatus> */
    public function status(): array
    {
        $this->ensureTrackingTable();

        $ran = [];
        $stmt = $this->connections->raw(
            "SELECT migration, batch, ran_at FROM {$this->table()} ORDER BY migration",
            [],
            $this->connection,
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ran[$row['migration']] = $row;
        }

        $files = $this->discover();
        $out   = [];
        foreach (array_keys($files) as $name) {
            if (isset($ran[$name])) {
                $out[] = new MigrationStatus(
                    connection: $this->connection,
                    name:       $name,
                    ran:        true,
                    batch:      (int) $ran[$name]['batch'],
                    ranAt:      (string) $ran[$name]['ran_at'],
                );
            } else {
                $out[] = new MigrationStatus(
                    connection: $this->connection,
                    name:       $name,
                    ran:        false,
                    batch:      null,
                    ranAt:      null,
                );
            }
        }
        return $out;
    }

    // ---------- internals ----------

    /** @return array<string, string> filename → absolute path, ordered by filename. */
    private function discover(): array
    {
        $cfg = $this->connections->config($this->connection);
        if (!$cfg instanceof ConnectionConfig || $cfg->migrationsPath === null) {
            throw new \LogicException(
                "Connection '{$this->connection}' has no migrationsPath configured."
            );
        }
        $path = $cfg->migrationsPath;

        if (!is_dir($path)) {
            return [];
        }

        $files = glob($path . '/*.php') ?: [];
        sort($files); // timestamp prefix gives total order.

        $out = [];
        foreach ($files as $abs) {
            $name = basename($abs, '.php');
            $out[$name] = $abs;
        }
        return $out;
    }

    /** @return list<array{name: string, path: string}> */
    private function pending(): array
    {
        $ran = $this->ranMigrationNames();
        $out = [];
        foreach ($this->discover() as $name => $path) {
            if (!in_array($name, $ran, true)) {
                $out[] = ['name' => $name, 'path' => $path];
            }
        }
        return $out;
    }

    /** @return list<string> */
    private function ranMigrationNames(): array
    {
        $stmt = $this->connections->raw(
            "SELECT migration FROM {$this->table()} ORDER BY migration",
            [],
            $this->connection,
        );
        /** @var list<string> $names */
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $names;
    }

    /** @param array{name: string, path: string} $entry */
    private function apply(array $entry, int $batch, bool $pretend): MigrationRun
    {
        $migration = $this->loadFile($entry['path']);

        $run = function () use ($migration, $entry, $batch): void {
            $migration->up();
            $this->connections->raw(
                "INSERT INTO {$this->table()} (migration, batch, ran_at) VALUES (?, ?, ?)",
                [$entry['name'], $batch, gmdate('Y-m-d H:i:s')],
                $this->connection,
            );
        };

        if ($pretend) {
            // No execution; just record the intent. Useful only for
            // status output; the actual SQL would be observable via
            // the QueryExecuted event in a future revision.
            return new MigrationRun($this->connection, $entry['name'], applied: false, pretended: true);
        }

        try {
            if ($migration->withinTransaction) {
                $this->tx->run($run, name: $this->connection);
            } else {
                $run();
            }
        } catch (Throwable $e) {
            throw new \RuntimeException("Migration {$entry['name']} on connection '{$this->connection}' failed: " . $e->getMessage(), $e->getCode(), previous: $e);
        }

        return new MigrationRun($this->connection, $entry['name'], applied: true, pretended: false);
    }

    private function revert(string $name, bool $pretend): MigrationRun
    {
        $files = $this->discover();
        if (!isset($files[$name])) {
            throw new \RuntimeException(
                "Migration '{$name}' was recorded on '{$this->connection}' but its file is missing."
            );
        }

        $migration = $this->loadFile($files[$name]);

        $run = function () use ($migration, $name): void {
            $migration->down();
            $this->connections->raw(
                "DELETE FROM {$this->table()} WHERE migration = ?",
                [$name],
                $this->connection,
            );
        };

        if ($pretend) {
            return new MigrationRun($this->connection, $name, applied: false, pretended: true);
        }

        if ($migration->withinTransaction) {
            $this->tx->run($run, name: $this->connection);
        } else {
            $run();
        }

        return new MigrationRun($this->connection, $name, applied: true, pretended: false);
    }

    private function loadFile(string $path): Migration
    {
        $migration = require $path;
        if (!$migration instanceof Migration) {
            throw new \RuntimeException(
                "Migration file '{$path}' must return an instance of " . Migration::class . '.'
            );
        }

        // If the migration declares an explicit connection different
        // from the directory's owner, honour it — but warn loudly
        // because that's almost always a mistake.
        $declared = $migration->connection();
        if ($declared !== null && $declared !== $this->connection) {
            throw new \LogicException(
                "Migration '{$path}' targets connection '{$declared}' but is "
                . "being loaded by the '{$this->connection}' migrator. Move the file "
                . "into the '{$declared}' migrations directory."
            );
        }

        return $migration;
    }

    private function ensureTrackingTable(): void
    {
        $table = $this->table();
        $sql = "CREATE TABLE IF NOT EXISTS {$table} ("
             . 'migration TEXT PRIMARY KEY, '
             . 'batch INTEGER NOT NULL, '
             . 'ran_at TEXT NOT NULL'
             . ')';
        $this->connections->exec($sql, $this->connection);
    }

    private function nextBatch(): int
    {
        $stmt = $this->connections->raw(
            "SELECT COALESCE(MAX(batch), 0) FROM {$this->table()}",
            [],
            $this->connection,
        );
        return ((int) $stmt->fetchColumn()) + 1;
    }

    /** @return list<int> */
    private function batchesDescending(int $limit): array
    {
        $stmt = $this->connections->raw(
            "SELECT DISTINCT batch FROM {$this->table()} ORDER BY batch DESC LIMIT ?",
            [$limit],
            $this->connection,
        );
        /** @var list<int> $batches */
        $batches = array_map(intval(...), $stmt->fetchAll(PDO::FETCH_COLUMN));
        return $batches;
    }

    /** @return list<string> */
    private function migrationsInBatch(int $batch): array
    {
        $stmt = $this->connections->raw(
            "SELECT migration FROM {$this->table()} WHERE batch = ? ORDER BY migration DESC",
            [$batch],
            $this->connection,
        );
        /** @var list<string> $names */
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $names;
    }

    private function dropAllTables(): void
    {
        // Sqlite-only for now (P2 is sqlite-only). Mysql/Pgsql variants
        // land in P5 alongside their schema grammars.
        $stmt = $this->connections->raw(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'",
            [],
            $this->connection,
        );
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
            $this->connections->exec(
                'DROP TABLE ' . $this->connections->driver($this->connection)->quoteIdentifier((string) $name),
                $this->connection,
            );
        }
    }

    private function table(): string
    {
        $cfg        = $this->connections->config($this->connection);
        $configured = $cfg?->migrationsTable !== null && $cfg->migrationsTable !== ''
            ? $cfg->migrationsTable
            : 'migrations';
        return $this->connections->driver($this->connection)->quoteIdentifier($configured);
    }
}

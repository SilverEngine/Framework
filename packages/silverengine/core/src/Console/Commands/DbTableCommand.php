<?php
declare(strict_types=1);

namespace Silver\Console\Commands;

use Silver\Console\Contracts\Command;
use Silver\Console\Input;
use Silver\Console\Output;
use Silver\Core\RuntimeContext;
use Silver\Orm\Connection\Driver;
use Silver\Orm\Schema\DatabaseBootstrap;

/**
 * `php silver db:table <name> [--connection=<conn>]` — column layout
 * and row count of one table.
 */
final class DbTableCommand implements Command
{
    public function description(): string
    {
        return 'Inspect a table: columns, types, nullability, row count';
    }

    public function handle(Input $input, Output $output): int
    {
        $table = $input->argument(0);
        if ($table === null || $table === '') {
            $output->error('Usage: php silver db:table <name> [--connection=<conn>]');
            return 1;
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            $output->error('Invalid table name.');
            return 1;
        }

        [$cm, , $defaultName] = DatabaseBootstrap::build(RuntimeContext::current()->root);
        $connection = (string) ($input->flag('connection') ?? $defaultName);
        if (!in_array($connection, $cm->names(), true)) {
            $output->error("Unknown connection: {$connection}");
            return 1;
        }

        $driver  = $cm->driver($connection);
        $columns = $this->columns($cm->pdo($connection), $driver, $table);
        if ($columns === []) {
            $output->error("Table '{$table}' not found on connection '{$connection}'.");
            return 1;
        }

        $quoted = $driver->quoteIdentifier($table);
        $count  = (int) $cm->raw("SELECT COUNT(*) FROM {$quoted}", [], $connection)->fetchColumn();

        $output->line("[{$connection}] {$table} — {$count} row(s)");
        $output->line();
        $output->table(['COLUMN', 'TYPE', 'NULLABLE', 'DEFAULT', 'KEY'], $columns);

        return 0;
    }

    /** @return list<list<string>> */
    private function columns(\PDO $pdo, Driver $driver, string $table): array
    {
        $rows = [];

        switch ($driver) {
            case Driver::Sqlite:
                $stmt = $pdo->query('PRAGMA table_info(' . $driver->quoteIdentifier($table) . ')');
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $c) {
                    $rows[] = [
                        (string) $c['name'],
                        (string) $c['type'],
                        $c['notnull'] ? 'no' : 'yes',
                        $c['dflt_value'] ?? '',
                        $c['pk'] ? 'PK' : '',
                    ];
                }
                break;

            case Driver::Mysql:
                $stmt = $pdo->query('SHOW COLUMNS FROM ' . $driver->quoteIdentifier($table));
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $c) {
                    $rows[] = [
                        (string) $c['Field'],
                        (string) $c['Type'],
                        $c['Null'] === 'YES' ? 'yes' : 'no',
                        $c['Default'] ?? '',
                        (string) $c['Key'],
                    ];
                }
                break;

            case Driver::Pgsql:
                $stmt = $pdo->prepare(
                    'SELECT column_name, data_type, is_nullable, column_default
                     FROM information_schema.columns
                     WHERE table_schema = \'public\' AND table_name = ?
                     ORDER BY ordinal_position',
                );
                $stmt->execute([$table]);
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $c) {
                    $rows[] = [
                        (string) $c['column_name'],
                        (string) $c['data_type'],
                        $c['is_nullable'] === 'YES' ? 'yes' : 'no',
                        $c['column_default'] ?? '',
                        '',
                    ];
                }
                break;
        }

        return $rows;
    }
}

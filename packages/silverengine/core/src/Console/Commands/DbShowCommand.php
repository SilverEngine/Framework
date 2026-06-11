<?php
declare(strict_types=1);

namespace Silver\Console\Commands;

use Silver\Console\Contracts\Command;
use Silver\Console\Input;
use Silver\Console\Output;
use Silver\Core\RuntimeContext;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Connection\Driver;
use Silver\Orm\Schema\DatabaseBootstrap;

/**
 * `php silver db:show` — connections overview: driver, dsn, tables.
 */
final class DbShowCommand implements Command
{
    public function description(): string
    {
        return 'Show configured database connections and their tables';
    }

    public function handle(Input $input, Output $output): int
    {
        [$cm, , $defaultName] = DatabaseBootstrap::build(RuntimeContext::current()->root);

        foreach ($cm->names() as $name) {
            $cfg   = $cm->config($name);
            $badge = $name === $defaultName ? ' (default)' : '';
            $output->line("[{$name}]{$badge}");
            $output->line('  driver:     ' . $cfg->driver->value);
            $output->line('  dsn:        ' . $cfg->dsn);
            $output->line('  migrations: ' . ($cfg->migrationsPath ?? '—') . " (table: {$cfg->migrationsTable})");

            try {
                $tables = $this->tables($cm, $name);
                $output->line('  tables:     ' . ($tables === [] ? '(none)' : implode(', ', $tables)));
            } catch (\Throwable $e) {
                $output->line('  tables:     unavailable — ' . $e->getMessage());
            }
            $output->line();
        }

        return 0;
    }

    /** @return list<string> */
    private function tables(ConnectionManager $cm, string $name): array
    {
        $sql = match ($cm->driver($name)) {
            Driver::Sqlite => "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name",
            Driver::Mysql  => 'SHOW TABLES',
            Driver::Pgsql  => "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename",
        };

        return $cm->raw($sql, [], $name)->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }
}

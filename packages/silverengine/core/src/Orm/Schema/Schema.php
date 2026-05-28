<?php
declare(strict_types=1);

namespace Silver\Orm\Schema;

use Closure;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Connection\Driver;
use Silver\Orm\Contracts\SchemaGrammarInterface;
use Silver\Orm\Schema\Grammar\MysqlSchemaGrammar;
use Silver\Orm\Schema\Grammar\PgsqlSchemaGrammar;
use Silver\Orm\Schema\Grammar\SqliteSchemaGrammar;

/**
 * Multi-connection schema facade. Static calls target the connection
 * pushed via {@see useConnection()} (or the default if none pushed);
 * the fluent {@see connection($name)} form returns a connection-scoped
 * proxy so migrations don't have to mutate global state.
 */
final class Schema
{
    private static ?ConnectionManager $connections = null;
    private static ?string            $current     = null;

    public static function bind(ConnectionManager $cm): void
    {
        self::$connections = $cm;
    }

    public static function useConnection(?string $name): void
    {
        self::$current = $name;
    }

    public static function currentConnection(): ?string
    {
        return self::$current;
    }

    public static function connection(string $name): SchemaForConnection
    {
        return new SchemaForConnection(self::cm(), $name);
    }

    // ---------- core operations (target the current connection) ----------

    public static function create(string $table, Closure $callback): void
    {
        self::run($table, $callback, Blueprint::ACTION_CREATE);
    }

    public static function table(string $table, Closure $callback): void
    {
        self::run($table, $callback, Blueprint::ACTION_ALTER);
    }

    public static function drop(string $table): void
    {
        $grammar = self::grammar();
        self::cm()->exec($grammar->compileDrop($table), self::$current);
    }

    public static function dropIfExists(string $table): void
    {
        $grammar = self::grammar();
        self::cm()->exec($grammar->compileDropIfExists($table), self::$current);
    }

    public static function rename(string $from, string $to): void
    {
        $grammar = self::grammar();
        self::cm()->exec($grammar->compileRename($from, $to), self::$current);
    }

    public static function hasTable(string $table): bool
    {
        $grammar = self::grammar();
        $stmt    = self::cm()->raw($grammar->compileHasTable($table), [], self::$current);
        return $stmt->fetch() !== false;
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $grammar = self::grammar();
        $stmt    = self::cm()->raw($grammar->compileHasColumn($table), [], self::$current);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if (($row['name'] ?? null) === $column) {
                return true;
            }
        }
        return false;
    }

    // ---------- internals ----------

    private static function run(string $table, Closure $cb, string $action): void
    {
        $bp = new Blueprint($table);
        $bp->action = $action;
        $cb($bp);

        $grammar = self::grammar();
        $stmts = $action === Blueprint::ACTION_CREATE
            ? $grammar->compileCreate($bp)
            : $grammar->compileAlter($bp);

        foreach ($stmts as $sql) {
            self::cm()->exec($sql, self::$current);
        }
    }

    private static function cm(): ConnectionManager
    {
        if (self::$connections === null) {
            throw new \LogicException(
                'Schema::bind(ConnectionManager) must be called before using the facade.'
            );
        }
        return self::$connections;
    }

    private static function grammar(): SchemaGrammarInterface
    {
        $driver = self::cm()->driver(self::$current);
        return self::grammarFor($driver);
    }

    public static function grammarFor(Driver $driver): SchemaGrammarInterface
    {
        return match ($driver) {
            Driver::Sqlite => new SqliteSchemaGrammar(),
            Driver::Mysql  => new MysqlSchemaGrammar(),
            Driver::Pgsql  => new PgsqlSchemaGrammar(),
        };
    }
}

<?php

declare(strict_types=1);

namespace Silver\Database;

use PDO;

/**
 * Owns the connection registry extracted from the Db God class:
 * named lazy PDO connections, the active default, and connection-level
 * primitives (raw/exec/quote/lastInsertId/driverName).
 *
 * State is process-global static — identical lifetime to the old
 * `Db::$dbs`/`Db::$default` so `connect()` at boot and reuse across the
 * request behave exactly as before. `Db` now delegates here; behaviour
 * is unchanged.
 */
final class ConnectionManager
{
    /** @var array<string, \Closure|PDO> */
    private static array $connections = [];

    private static ?string $default = null;

    public static function connect(string $name, string $dsn, ?string $username = null, ?string $password = null): void
    {
        self::$connections[$name] = function () use ($dsn, $username, $password): PDO {
            $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

            if (str_starts_with($dsn, 'mysql:') && defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES 'utf8'";
            }

            return new PDO($dsn, $username, $password, $options);
        };
    }

    /** @throws \Exception */
    public static function setDefault(string $name): void
    {
        if (!isset(self::$connections[$name])) {
            throw new \Exception("Connection '$name' not found.");
        }
        self::$default = $name;
    }

    public static function defaultName(): ?string
    {
        return self::$default;
    }

    public static function withConnection(string $name, callable $cb): void
    {
        $prev = self::$default;
        self::setDefault($name);
        try {
            $cb();
        } finally {
            self::$default = $prev;
        }
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::$connections);
    }

    /** @throws \Exception */
    public static function pdo(?string $name = null): PDO
    {
        if ($name === null) {
            $name = self::$default;
        }

        if ($name === null) {
            throw new \Exception("Not default connection found.");
        }

        $db = self::$connections[$name] ?? null;

        // Lazy loading — memoize the resolved PDO in place.
        if ($db && is_callable($db)) {
            $db = self::$connections[$name] = $db();
        }

        if (!$db) {
            throw new \Exception("Connection '$name' not found.");
        }

        return $db;
    }

    public static function driverName(): string
    {
        return self::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * @return string|int|float quoted string, or numeric value unchanged
     * @throws \Exception on unsupported value type
     */
    public static function quote($value)
    {
        switch ($type = gettype($value)) {
        case 'string':
            return self::pdo()->quote($value);
        case 'integer':
        case 'double':
            return $value;
        default:
            throw new \Exception("Unable to quote value with type: $type");
        }
    }

    /** @param array $bindings */
    public static function raw($sql, $bindings = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($bindings);

        return $stmt;
    }

    public static function exec($sql): int|false
    {
        return self::pdo()->exec($sql);
    }

    public static function lastInsertId(): string|false
    {
        return self::pdo()->lastInsertId();
    }
}

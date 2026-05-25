<?php

declare(strict_types=1);

namespace Silver\Database;

use PDO;

/**
 * Owns the connection registry: named lazy PDO connections, the active
 * default, and connection-level primitives (raw/exec/quote/lastInsertId/
 * driverName). Resolved as a singleton through the container so the
 * lifetime matches the old static state — `connect()` at boot, reused
 * across the request.
 *
 * Public users of the DB still go through {@see Db}, which holds an
 * internal reference to this singleton.
 */
final class ConnectionManager
{
    /** @var array<string, \Closure|PDO> */
    private array $connections = [];

    private ?string $default = null;

    public function connect(string $name, string $dsn, ?string $username = null, ?string $password = null): void
    {
        $this->connections[$name] = function () use ($dsn, $username, $password): PDO {
            $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

            if (str_starts_with($dsn, 'mysql:') && defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES 'utf8'";
            }

            return new PDO($dsn, $username, $password, $options);
        };
    }

    /** @throws \Exception */
    public function setDefault(string $name): void
    {
        if (!isset($this->connections[$name])) {
            throw new \Exception("Connection '$name' not found.");
        }
        $this->default = $name;
    }

    public function defaultName(): ?string
    {
        return $this->default;
    }

    public function withConnection(string $name, callable $cb): void
    {
        $prev = $this->default;
        $this->setDefault($name);
        try {
            $cb();
        } finally {
            $this->default = $prev;
        }
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->connections);
    }

    /** @throws \Exception */
    public function pdo(?string $name = null): PDO
    {
        if ($name === null) {
            $name = $this->default;
        }

        if ($name === null) {
            throw new \Exception("Not default connection found.");
        }

        $db = $this->connections[$name] ?? null;

        // Lazy loading — memoize the resolved PDO in place.
        if ($db && is_callable($db)) {
            $db = $this->connections[$name] = $db();
        }

        if (!$db) {
            throw new \Exception("Connection '$name' not found.");
        }

        return $db;
    }

    public function driverName(): string
    {
        return $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * @return string|int|float quoted string, or numeric value unchanged
     * @throws \Exception on unsupported value type
     */
    public function quote($value)
    {
        switch ($type = gettype($value)) {
        case 'string':
            return $this->pdo()->quote($value);
        case 'integer':
        case 'double':
            return $value;
        default:
            throw new \Exception("Unable to quote value with type: $type");
        }
    }

    /** @param array $bindings */
    public function raw($sql, $bindings = []): \PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);

        return $stmt;
    }

    public function exec($sql): int|false
    {
        return $this->pdo()->exec($sql);
    }

    public function lastInsertId(): string|false
    {
        return $this->pdo()->lastInsertId();
    }
}

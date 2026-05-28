<?php
declare(strict_types=1);

namespace Silver\Orm\Connection;

use Closure;
use PDO;
use PDOStatement;
use Silver\Orm\Contracts\ConnectionInterface;

/**
 * Owns the connection registry: named lazy PDO connections, the active
 * default, and connection-level primitives. Resolved as a singleton
 * through the container so its lifetime matches the request.
 */
class ConnectionManager implements ConnectionInterface
{
    /** @var array<string, Closure|PDO> */
    private array $connections = [];

    /** @var array<string, ConnectionConfig> */
    private array $configs = [];

    private ?string $default = null;

    private bool $debug = false;

    /**
     * Register a connection. Old signature kept as the primary form for
     * back-compat with Db::connect() callers. Use {@see registerConfig()}
     * for the typed form that carries migration metadata.
     */
    public function connect(string $name, string $dsn, ?string $username = null, ?string $password = null): void
    {
        $driver = self::driverFromDsn($dsn);
        $this->registerConfig($name, new ConnectionConfig(
            driver:   $driver,
            dsn:      $dsn,
            username: $username,
            password: $password,
        ));
    }

    public function registerConfig(string $name, ConnectionConfig $config): void
    {
        $this->configs[$name] = $config;
        $this->connections[$name] = function () use ($config): PDO {
            $options = $config->options + [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ];

            if ($config->driver === Driver::Mysql && defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] ??= "SET NAMES 'utf8mb4'";
            }

            return new PDO($config->dsn, $config->username, $config->password, $options);
        };
    }

    public function setDefault(string $name): void
    {
        if (!isset($this->connections[$name])) {
            throw ConnectionException::notFound($name);
        }
        $this->default = $name;
    }

    public function defaultName(): string
    {
        if ($this->default === null) {
            throw ConnectionException::noDefault();
        }
        return $this->default;
    }

    /**
     * Scoped switch of the default connection for the duration of $cb.
     * Restored on success and on exception.
     */
    public function withConnection(string $name, callable $cb): mixed
    {
        $prev = $this->default;
        $this->setDefault($name);
        try {
            return $cb();
        } finally {
            $this->default = $prev;
        }
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->connections);
    }

    public function pdo(?string $name = null): PDO
    {
        $name ??= $this->defaultName();

        if (!isset($this->connections[$name])) {
            throw ConnectionException::notFound($name);
        }

        $db = $this->connections[$name];
        if ($db instanceof Closure) {
            $db = $this->connections[$name] = $db();
        }

        return $db;
    }

    public function driver(?string $name = null): Driver
    {
        $name ??= $this->defaultName();
        if (isset($this->configs[$name])) {
            return $this->configs[$name]->driver;
        }
        return Driver::from($this->pdo($name)->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    public function config(?string $name = null): ?ConnectionConfig
    {
        $name ??= $this->defaultName();
        return $this->configs[$name] ?? null;
    }

    /**
     * Legacy string form. Returns the PDO driver name verbatim.
     * Prefer {@see driver()} which returns the typed enum.
     */
    public function driverName(?string $name = null): string
    {
        return $this->driver($name)->value;
    }

    public function quote(mixed $value, ?string $name = null): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            return $this->pdo($name)->quote($value);
        }
        throw ConnectionException::unquotable(get_debug_type($value));
    }

    /**
     * Prepare + execute a raw SQL statement with bindings.
     * Echoes the SQL when {@see isDebug()} is on.
     *
     * @param array<int|string, mixed> $bindings
     */
    public function raw(string $sql, array $bindings = [], ?string $name = null): PDOStatement
    {
        if ($this->debug) {
            self::echoSql($sql, $bindings);
        }
        $stmt = $this->pdo($name)->prepare($sql);
        $stmt->execute($bindings);
        return $stmt;
    }

    public function exec(string $sql, ?string $name = null): int
    {
        if ($this->debug) {
            self::echoSql($sql, []);
        }
        $result = $this->pdo($name)->exec($sql);
        return $result === false ? 0 : $result;
    }

    public function lastInsertId(?string $name = null): string
    {
        $id = $this->pdo($name)->lastInsertId();
        return $id === false ? '' : $id;
    }

    public function setDebug(bool $enabled): void
    {
        $this->debug = $enabled;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    private static function driverFromDsn(string $dsn): Driver
    {
        $prefix = strstr($dsn, ':', true);
        return match ($prefix) {
            'sqlite' => Driver::Sqlite,
            'mysql'  => Driver::Mysql,
            'pgsql'  => Driver::Pgsql,
            default  => throw new ConnectionException("Unsupported DSN prefix '{$prefix}'."),
        };
    }

    /** @param array<int|string, mixed> $bindings */
    private static function echoSql(string $sql, array $bindings): void
    {
        echo $sql;
        if ($bindings !== []) {
            echo ' -- ' . json_encode(array_values($bindings));
        }
        echo PHP_EOL;
    }
}

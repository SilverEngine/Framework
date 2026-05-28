<?php
declare(strict_types=1);

namespace Silver\Orm\Schema;

use Silver\Core\Env;
use Silver\Orm\Connection\ConnectionConfig;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Connection\Driver;
use Silver\Orm\Connection\TransactionManager;

/**
 * Bootstraps the connection registry for the migration CLI commands.
 *
 * Supports two config shapes for portability across the rollout:
 *
 *   1. New shape (preferred):
 *      'connections' => [
 *          'default'   => ['driver' => 'sqlite', 'database' => '…', 'migrations' => '…'],
 *          'warehouse' => ['driver' => 'pgsql',  …],
 *      ],
 *      'default' => 'default',
 *
 *   2. Legacy shape (current databases.php):
 *      'default' => 'local',
 *      'local'   => ['driver' => 'mysql', 'database' => '…', …],
 *
 * Both yield a fully populated ConnectionManager with per-connection
 * migration metadata.
 */
final class CliBootstrap
{
    public static function build(string $root): array
    {
        if (!defined('ROOT')) {
            define('ROOT', rtrim($root, '/') . '/');
        }
        Env::construct(ROOT);

        $cm  = new ConnectionManager();
        $cfg = self::toArray(Env::get('databases'));
        if (!is_array($cfg) || $cfg === []) {
            throw new \RuntimeException('No databases config found.');
        }

        $connections = self::normalize($cfg, $root);
        if ($connections === []) {
            throw new \RuntimeException(
                'No database connections defined. Add one under databases.connections in config/databases.php.'
            );
        }

        $defaultName = (string) ($cfg['default'] ?? array_key_first($connections));
        foreach ($connections as $name => $conn) {
            $cm->registerConfig($name, $conn);
        }
        if (!isset($connections[$defaultName])) {
            $defaultName = array_key_first($connections);
        }
        $cm->setDefault($defaultName);

        $tx = new TransactionManager($cm);
        Schema::bind($cm);

        return [$cm, $tx, $defaultName];
    }

    /**
     * @param array<string, mixed> $cfg
     * @return array<string, ConnectionConfig>
     */
    private static function normalize(array $cfg, string $root): array
    {
        $raw = $cfg['connections'] ?? null;
        if (!is_array($raw)) {
            // Legacy shape: every non-reserved string key whose value
            // is an array is a connection.
            $raw = [];
            foreach ($cfg as $k => $v) {
                if (in_array($k, ['on', 'default', 'connections'], true)) {
                    continue;
                }
                if (is_array($v)) {
                    $raw[$k] = $v;
                }
            }
        }

        $out = [];
        foreach ($raw as $name => $conn) {
            $out[(string) $name] = self::toConfig((string) $name, (array) $conn, $root);
        }
        return $out;
    }

    /** @param array<string, mixed> $conn */
    private static function toConfig(string $name, array $conn, string $root): ConnectionConfig
    {
        $driver = Driver::from((string) ($conn['driver'] ?? 'sqlite'));
        $dsn    = self::dsn($driver, $conn, $root);

        $migrationsPath = isset($conn['migrations'])
            ? self::resolvePath((string) $conn['migrations'], $root)
            : self::defaultMigrationsPath($name, $root);

        return new ConnectionConfig(
            driver:          $driver,
            dsn:             $dsn,
            username:        isset($conn['username']) ? (string) $conn['username'] : null,
            password:        isset($conn['password']) ? (string) $conn['password'] : null,
            migrationsPath:  $migrationsPath,
            migrationsTable: (string) ($conn['migrations_table'] ?? 'migrations'),
        );
    }

    /** @param array<string, mixed> $conn */
    private static function dsn(Driver $driver, array $conn, string $root): string
    {
        if (isset($conn['dsn'])) {
            return (string) $conn['dsn'];
        }

        $db   = (string) ($conn['database'] ?? '');
        $host = (string) ($conn['hostname'] ?? $conn['host'] ?? 'localhost');
        $port = (string) ($conn['port'] ?? '');

        return match ($driver) {
            Driver::Sqlite => 'sqlite:' . self::resolvePath($db ?: 'database/database.sqlite', $root),
            Driver::Mysql  => "mysql:host={$host}" . ($port !== '' && $port !== '0' ? ";port={$port}" : '') . ";dbname={$db};charset=utf8mb4",
            Driver::Pgsql  => "pgsql:host={$host}" . ($port !== '' && $port !== '0' ? ";port={$port}" : '') . ";dbname={$db}",
        };
    }

    private static function resolvePath(string $path, string $root): string
    {
        $root = rtrim($root, '/') . '/';
        if ($path === '' || $path[0] === '/') {
            return $path;
        }
        return $root . $path;
    }

    private static function defaultMigrationsPath(string $connection, string $root): string
    {
        $root = rtrim($root, '/') . '/';
        return $connection === 'default' || $connection === 'local'
            ? $root . 'database/migrations'
            : $root . 'database/migrations/' . $connection;
    }

    /**
     * Env::get() returns stdClass for assoc shapes (json_decode-style).
     * Recursively cast back to plain associative arrays for the rest
     * of this bootstrapper's array-based logic.
     */
    private static function toArray(mixed $v): mixed
    {
        if (is_object($v)) {
            $v = (array) $v;
        }
        if (is_array($v)) {
            foreach ($v as $k => $vv) {
                $v[$k] = self::toArray($vv);
            }
        }
        return $v;
    }
}

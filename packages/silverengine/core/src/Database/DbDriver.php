<?php

declare(strict_types=1);

namespace Silver\Database;

/**
 * Database drivers SilverEngine builds a DSN for. Backed values match the
 * `driver` strings in the `databases` config and PDO's driver names.
 */
enum DbDriver: string
{
    case Sqlite = 'sqlite';
    case Mysql  = 'mysql';
    case Pgsql  = 'pgsql';

    /**
     * Build a PDO DSN for a configured connection. `$cfg` is the resolved
     * `databases.local` config object (`->database`, `->hostname`,
     * `->basename`). Unknown drivers fall back to a host-based DSN —
     * preserving the pre-enum `default` branch behaviour exactly.
     */
    public static function dsn(string $driver, string $root, object $cfg): string
    {
        return match (self::tryFrom($driver)) {
            self::Sqlite => 'sqlite:' . $root . $cfg->database,
            default      => $driver . ':host=' . $cfg->hostname
                            . ';dbname=' . $cfg->basename . ';charset=utf8',
        };
    }
}

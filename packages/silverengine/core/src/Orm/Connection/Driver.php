<?php
declare(strict_types=1);

namespace Silver\Orm\Connection;

enum Driver: string
{
    case Sqlite = 'sqlite';
    case Mysql  = 'mysql';
    case Pgsql  = 'pgsql';

    public function quoteIdentifier(string $name): string
    {
        return match ($this) {
            self::Mysql  => '`' . str_replace('`', '``', $name) . '`',
            default      => '"' . str_replace('"', '""', $name) . '"',
        };
    }
}

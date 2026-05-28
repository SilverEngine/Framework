<?php

declare(strict_types=1);

namespace Silver\Engine;

/**
 * Top-level `silver` CLI commands. Backed values are the canonical
 * tokens; `parse()` additionally accepts the legacy `c` alias for
 * `g` (generate).
 */
enum Command: string
{
    case Generate         = 'g';
    case Delete           = 'd';
    case Migrate          = 'migrate';
    case MigrateRollback  = 'migrate:rollback';
    case MigrateReset     = 'migrate:reset';
    case MigrateFresh     = 'migrate:fresh';
    case MigrateStatus    = 'migrate:status';
    case MakeMigration    = 'make:migration';
    case Serve            = 'serve';
    case Optimize         = 'optimize';
    case OptimizeClear    = 'optimize:clear';
    case KeyGenerate      = 'key:generate';
    case Help             = 'help';

    public static function parse(string $cmd): ?self
    {
        return $cmd === 'c' ? self::Generate : self::tryFrom($cmd);
    }
}

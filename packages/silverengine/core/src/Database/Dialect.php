<?php

declare(strict_types=1);

namespace Silver\Database;

/**
 * Resolves the driver-specific compile class for a base Parts/Query
 * class. Convention: splice the driver segment before the class short
 * name — `Silver\Database\Parts\ColumnDef` →
 * `Silver\Database\Parts\Sqlite\ColumnDef` — falling back to the base
 * class when no dialect variant exists.
 *
 * This is the exact rule the inline `Compiler::toSql()` logic used
 * (`ucfirst(Db::driverName())` + `substr_replace` + `class_exists`),
 * extracted into one typed, unit-tested place so dialect dispatch is no
 * longer an obscure expression in the hot compile path. {@see DbDriver}
 * supplies the typed segment for the known driver set; `ucfirst()` still
 * governs any unknown driver, so behaviour is identical.
 */
final class Dialect
{
    public static function segment(?string $driver): string
    {
        $driver ??= '';

        return DbDriver::tryFrom($driver)?->name ?? ucfirst($driver);
    }

    public static function classFor(string $baseClass, ?string $driver): string
    {
        $segment = self::segment($driver);
        if ($segment === '') {
            return $baseClass;
        }

        $pos = strrpos($baseClass, '\\') ?: 0;
        $dialectClass = substr_replace($baseClass, '\\' . $segment, $pos, 0);

        return class_exists($dialectClass) ? $dialectClass : $baseClass;
    }
}

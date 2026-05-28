<?php
declare(strict_types=1);

namespace Silver\Orm\Relations\Morph;

/**
 * FQN ↔ short alias map for polymorphic morph_type columns.
 *
 *   TypeMap::register('user', \App\Models\User::class);
 *   TypeMap::register('post', \App\Models\Post::class);
 *
 * Tables store the alias ("user", "post") instead of the full
 * `App\Models\User` so renames/namespace moves don't rewrite data.
 * If no alias is registered for a class, the FQN is used as-is.
 */
final class TypeMap
{
    /** @var array<string, string> alias → FQN */
    private static array $aliasToClass = [];

    /** @var array<string, string> FQN → alias */
    private static array $classToAlias = [];

    /** @param class-string $class */
    public static function register(string $alias, string $class): void
    {
        self::$aliasToClass[$alias] = $class;
        self::$classToAlias[$class] = $alias;
    }

    /** @param class-string $class */
    public static function aliasFor(string $class): string
    {
        return self::$classToAlias[$class] ?? $class;
    }

    /** @return class-string */
    public static function classFor(string $alias): string
    {
        return self::$aliasToClass[$alias] ?? $alias;
    }

    public static function flush(): void
    {
        self::$aliasToClass = [];
        self::$classToAlias = [];
    }
}

<?php
declare(strict_types=1);

namespace Silver\Http;

/**
 * Static facade over the container-resolved {@see CookieJar} singleton.
 * `Cookie::set(...)` returns the jar, so the `set(...)->encrypt()`
 * chain keeps working.
 */
final class Cookie
{
    public static function set(string $name, mixed $value, int $expire = 0, bool $extend = false): CookieJar
    {
        return self::jar()->set($name, $value, $expire, $extend);
    }

    public static function get(string $name, ?string $returnType = null): mixed
    {
        return self::jar()->get($name, $returnType);
    }

    public static function isCrypted(string $name): bool
    {
        return self::jar()->isCrypted($name);
    }

    public static function attach(string $key, mixed $newValue): bool
    {
        return self::jar()->attach($key, $newValue);
    }

    public static function detach(string $key, string|int $valueToDelete): bool
    {
        return self::jar()->detach($key, $valueToDelete);
    }

    public static function all(): array
    {
        return self::jar()->all();
    }

    public static function exists(string $key): bool
    {
        return self::jar()->exists($key);
    }

    public static function delete(string $key): void
    {
        self::jar()->delete($key);
    }

    public static function flush(): void
    {
        self::jar()->flush();
    }

    private static function jar(): CookieJar
    {
        return app(CookieJar::class);
    }
}

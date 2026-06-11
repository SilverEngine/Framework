<?php
declare(strict_types=1);

namespace Silver\Http;

use Silver\Http\Contracts\SessionStore;

/**
 * Static facade over the container-resolved {@see SessionStore}
 * singleton. Call sites keep the `Session::get()` API; the backend is
 * swappable (native $_SESSION by default, {@see ArraySessionStore} in
 * tests) via `app()->instance(SessionStore::class, …)`.
 */
final class Session
{
    /** Rotate flash → old-flash at request boot (one-request lifetime). */
    public static function construct(): void
    {
        self::store()->rotateFlash();
    }

    public static function all(): array
    {
        return self::store()->all();
    }

    public static function set(string $key, mixed $value): mixed
    {
        return self::store()->set($key, $value);
    }

    public static function flash(string $key, mixed $value): mixed
    {
        return self::store()->flash($key, $value);
    }

    public static function exists(string $name): bool
    {
        return self::store()->exists($name);
    }

    public static function get(string $name, mixed $default = null): mixed
    {
        return self::store()->get($name, $default);
    }

    public static function delete(string $key): void
    {
        self::store()->delete($key);
    }

    public static function flush(): void
    {
        self::store()->flush();
    }

    public static function kill(): bool
    {
        return self::store()->kill();
    }

    private static function store(): SessionStore
    {
        return app(SessionStore::class);
    }
}

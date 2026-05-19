<?php
declare(strict_types=1);

namespace Silver\Http;

final class Session
{
    public static function construct(): void
    {
        $_SESSION['data'] ??= [];
        $_SESSION['old-flash'] ??= [];
        $_SESSION['flash'] ??= [];

        $_SESSION['old-flash'] = $_SESSION['flash'];
        $_SESSION['flash'] = [];
    }

    public static function all(): array
    {
        return array_merge(
            $_SESSION['data'] ?? [],
            $_SESSION['old-flash'] ?? [],
            $_SESSION['flash'] ?? [],
        );
    }

    public static function set(string $key, mixed $value): mixed
    {
        self::delete($key);
        return $_SESSION['data'][$key] = $value;
    }

    public static function flash(string $key, mixed $value): mixed
    {
        self::delete($key);
        return $_SESSION['flash'][$key] = $value;
    }

    public static function exists(string $name): bool
    {
        return isset(self::all()[$name]);
    }

    public static function get(string $name, mixed $default = null): mixed
    {
        return self::exists($name) ? self::all()[$name] : $default;
    }

    public static function delete(string $key): void
    {
        unset(
            $_SESSION['data'][$key],
            $_SESSION['old-flash'][$key],
            $_SESSION['flash'][$key],
        );
    }

    public static function flush(): void
    {
        $_SESSION = [];
    }

    public static function kill(): bool
    {
        return session_destroy();
    }
}

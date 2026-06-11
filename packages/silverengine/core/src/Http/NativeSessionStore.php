<?php
declare(strict_types=1);

namespace Silver\Http;

use Silver\Http\Contracts\SessionStore;

/**
 * Default session backend over PHP's native $_SESSION. Assumes
 * session_start() has been called by the entrypoint.
 */
final class NativeSessionStore implements SessionStore
{
    public function rotateFlash(): void
    {
        $_SESSION['data']      ??= [];
        $_SESSION['old-flash'] ??= [];
        $_SESSION['flash']     ??= [];

        $_SESSION['old-flash'] = $_SESSION['flash'];
        $_SESSION['flash']     = [];
    }

    public function all(): array
    {
        return array_merge(
            $_SESSION['data'] ?? [],
            $_SESSION['old-flash'] ?? [],
            $_SESSION['flash'] ?? [],
        );
    }

    public function set(string $key, mixed $value): mixed
    {
        $this->delete($key);
        return $_SESSION['data'][$key] = $value;
    }

    public function flash(string $key, mixed $value): mixed
    {
        $this->delete($key);
        return $_SESSION['flash'][$key] = $value;
    }

    public function exists(string $name): bool
    {
        return isset($this->all()[$name]);
    }

    public function get(string $name, mixed $default = null): mixed
    {
        return $this->exists($name) ? $this->all()[$name] : $default;
    }

    public function delete(string $key): void
    {
        unset(
            $_SESSION['data'][$key],
            $_SESSION['old-flash'][$key],
            $_SESSION['flash'][$key],
        );
    }

    public function flush(): void
    {
        $_SESSION = [];
    }

    public function kill(): bool
    {
        return session_destroy();
    }
}

<?php
declare(strict_types=1);

namespace Silver\Http;

use Silver\Http\Contracts\SessionStore;

/**
 * In-memory session backend — no superglobals, no session extension.
 * Swap it in for tests:
 *
 *     app()->instance(SessionStore::class, new ArraySessionStore());
 */
final class ArraySessionStore implements SessionStore
{
    /** @var array<string, mixed> */
    private array $data = [];
    /** @var array<string, mixed> */
    private array $flash = [];
    /** @var array<string, mixed> */
    private array $oldFlash = [];

    public function rotateFlash(): void
    {
        $this->oldFlash = $this->flash;
        $this->flash    = [];
    }

    public function all(): array
    {
        return array_merge($this->data, $this->oldFlash, $this->flash);
    }

    public function set(string $key, mixed $value): mixed
    {
        $this->delete($key);
        return $this->data[$key] = $value;
    }

    public function flash(string $key, mixed $value): mixed
    {
        $this->delete($key);
        return $this->flash[$key] = $value;
    }

    public function exists(string $name): bool
    {
        return isset($this->all()[$name]);
    }

    public function get(string $name, mixed $default = null): mixed
    {
        return $this->all()[$name] ?? $default;
    }

    public function delete(string $key): void
    {
        unset($this->data[$key], $this->oldFlash[$key], $this->flash[$key]);
    }

    public function flush(): void
    {
        $this->data = $this->flash = $this->oldFlash = [];
    }

    public function kill(): bool
    {
        $this->flush();
        return true;
    }
}

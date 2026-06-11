<?php
declare(strict_types=1);

namespace Silver\Http\Contracts;

/**
 * Session backend contract. The `Session` static class is a thin facade
 * over the container-resolved store, so swapping the backend (native
 * PHP session, in-memory array for tests, …) never touches call sites.
 *
 * Three buckets: `data` (persistent), `flash` (written this request),
 * `old-flash` (flashed last request, readable this request). A call to
 * {@see rotateFlash()} at request boot promotes flash → old-flash so
 * flashed values live for exactly one following request.
 */
interface SessionStore
{
    /** Promote flash → old-flash and clear the new-flash bucket. */
    public function rotateFlash(): void;

    /** @return array<string, mixed> data + old-flash + flash merged */
    public function all(): array;

    public function set(string $key, mixed $value): mixed;

    public function flash(string $key, mixed $value): mixed;

    public function exists(string $name): bool;

    public function get(string $name, mixed $default = null): mixed;

    public function delete(string $key): void;

    public function flush(): void;

    public function kill(): bool;
}

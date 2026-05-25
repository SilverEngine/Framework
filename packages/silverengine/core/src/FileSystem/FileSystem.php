<?php
declare(strict_types=1);

namespace Silver\FileSystem;

/**
 * Filesystem service contract. The default binding is
 * {@see LocalFileSystem}, registered in `config/Services.php`. Swap or
 * decorate via the container (`$c->bind(FileSystem::class, …)` /
 * `$c->extend(FileSystem::class, …)`).
 *
 * Every method on the local implementation is hookable — see
 * {@see \Silver\Core\Hook} and the framework's hook docs.
 */
interface FileSystem
{
    public function exists(string $path): bool;

    public function isFile(string $path): bool;

    public function isDir(string $path): bool;

    public function read(string $path): string;

    /** @return int bytes written */
    public function write(string $path, string $contents, bool $lock = false): int;

    public function append(string $path, string $contents): int;

    public function delete(string $path): bool;

    public function move(string $from, string $to): bool;

    public function mkdir(string $path, int $mode = 0o775, bool $recursive = true): bool;

    /** @return list<string> file paths (no directories) */
    public function listing(string $dir, string $pattern = '*'): array;

    public function size(string $path): int;

    public function mtime(string $path): int;
}

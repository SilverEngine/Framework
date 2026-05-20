<?php
declare(strict_types=1);

namespace Silver\FileSystem;

use Silver\Concerns\Hookable;
use Silver\Exception\Exception;

/**
 * Default {@see FileSystem} implementation, backed by the native PHP
 * file API. Every public method is wrapped in {@see Hookable::intercept()}
 * so callers can register before / after / deferred hooks on
 * `FileSystem::class` (or `LocalFileSystem::class`).
 */
final class LocalFileSystem implements FileSystem
{
    use Hookable;

    public function exists(string $path): bool
    {
        return $this->intercept(__FUNCTION__, [$path], fn (string $p): bool => file_exists($p));
    }

    public function isFile(string $path): bool
    {
        return $this->intercept(__FUNCTION__, [$path], fn (string $p): bool => is_file($p));
    }

    public function isDir(string $path): bool
    {
        return $this->intercept(__FUNCTION__, [$path], fn (string $p): bool => is_dir($p));
    }

    public function read(string $path): string
    {
        return $this->intercept(__FUNCTION__, [$path], static function (string $p): string {
            $contents = @file_get_contents($p);
            if ($contents === false) {
                throw new Exception("FileSystem: cannot read '{$p}'.");
            }
            return $contents;
        });
    }

    public function write(string $path, string $contents, bool $lock = false): int
    {
        return $this->intercept(__FUNCTION__, [$path, $contents, $lock], static function (string $p, string $c, bool $l): int {
            $bytes = file_put_contents($p, $c, $l ? LOCK_EX : 0);
            return $bytes === false ? 0 : $bytes;
        });
    }

    public function append(string $path, string $contents): int
    {
        return $this->intercept(__FUNCTION__, [$path, $contents], static function (string $p, string $c): int {
            $bytes = file_put_contents($p, $c, FILE_APPEND | LOCK_EX);
            return $bytes === false ? 0 : $bytes;
        });
    }

    public function delete(string $path): bool
    {
        return $this->intercept(__FUNCTION__, [$path], static fn (string $p): bool => @unlink($p));
    }

    public function move(string $from, string $to): bool
    {
        return $this->intercept(__FUNCTION__, [$from, $to], static fn (string $f, string $t): bool => @rename($f, $t));
    }

    public function mkdir(string $path, int $mode = 0o775, bool $recursive = true): bool
    {
        return $this->intercept(__FUNCTION__, [$path, $mode, $recursive], static function (string $p, int $m, bool $r): bool {
            if (is_dir($p)) {
                return true;
            }
            return @mkdir($p, $m, $r) || is_dir($p);
        });
    }

    public function listing(string $dir, string $pattern = '*'): array
    {
        return $this->intercept(__FUNCTION__, [$dir, $pattern], static function (string $d, string $pat): array {
            $d = rtrim($d, '/');
            $found = glob($d . '/' . $pat) ?: [];
            return array_values(array_filter($found, 'is_file'));
        });
    }

    public function size(string $path): int
    {
        return $this->intercept(__FUNCTION__, [$path], static function (string $p): int {
            $s = @filesize($p);
            return $s === false ? 0 : $s;
        });
    }

    public function mtime(string $path): int
    {
        return $this->intercept(__FUNCTION__, [$path], static function (string $p): int {
            $m = @filemtime($p);
            return $m === false ? 0 : $m;
        });
    }
}

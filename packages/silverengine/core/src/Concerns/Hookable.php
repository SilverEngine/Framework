<?php
declare(strict_types=1);

namespace Silver\Concerns;

use Closure;
use Silver\Core\App;
use Silver\Core\Hook;

/**
 * Mix-in for services that want to fire `before` / `after` hooks at
 * chosen dispatch points. Call {@see self::intercept()} from the methods
 * you want hookable; methods you don't wrap stay zero-overhead.
 *
 * Example:
 *
 *     final class LocalFileSystem implements FileSystem
 *     {
 *         use Hookable;
 *
 *         public function write(string $path, string $contents): int
 *         {
 *             return $this->intercept(__FUNCTION__, [$path, $contents],
 *                 fn (string $p, string $c): int => (int) file_put_contents($p, $c),
 *             );
 *         }
 *     }
 */
trait Hookable
{
    /** @param array<int,mixed> $args */
    protected function intercept(string $method, array $args, Closure $inner): mixed
    {
        return app(Hook::class)
            ->intercept($this, $method, $args, $inner);
    }
}

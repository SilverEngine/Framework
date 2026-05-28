<?php
declare(strict_types=1);

namespace Silver\Engine\Ghost;

/**
 * On-disk compile cache for Ghost templates.
 *
 * Each template source is compiled once into `storage/cache/views/<hash>.php`
 * and re-included on subsequent renders. Compiled files are plain PHP, so
 * opcache picks them up the same as any other framework code.
 *
 * Invalidation is dependency-aware: every source file visited during a
 * compile (the template itself plus anything it includes / extends /
 * components into) is stamped with its mtime in the cached file's header.
 * On lookup we re-check every dep; a single mismatch forces recompile.
 *
 * Tracking is done via a static frame stack — see `Template::render()`.
 * When a fresh cache hit is served the cached deps are bubbled into the
 * active frame, so a parent that uses an unchanged child still inherits
 * the child's transitive dependencies.
 */
final class Compiler
{
    /** Bump when the compiler output format changes; invalidates all caches. */
    private const VERSION = 1;

    /** @var list<array<string,int>> */
    private static array $depsStack = [];

    public static function cacheDir(): string
    {
        $root = defined('ROOT') ? \ROOT : (getcwd() . '/');
        return rtrim($root, '/') . '/storage/cache/views/';
    }

    public static function pathFor(string $sourceFile): string
    {
        $hash = substr(hash('xxh128', $sourceFile), 0, 24);
        return self::cacheDir() . $hash . '.php';
    }

    public static function startFrame(): void
    {
        self::$depsStack[] = [];
    }

    /**
     * Pop the current frame and merge its deps into the parent frame (if any).
     * Returns the popped frame for the caller to persist alongside the
     * compiled artifact.
     *
     * @return array<string,int>
     */
    public static function endFrame(): array
    {
        $deps = array_pop(self::$depsStack) ?? [];
        if (self::$depsStack !== []) {
            $top = array_key_last(self::$depsStack);
            self::$depsStack[$top] = array_merge(self::$depsStack[$top], $deps);
        }
        return $deps;
    }

    public static function track(string $file): void
    {
        if (self::$depsStack === [] || !is_file($file)) {
            return;
        }
        $top = array_key_last(self::$depsStack);
        self::$depsStack[$top][$file] = filemtime($file);
    }

    /** @param array<string,int> $deps */
    public static function trackMany(array $deps): void
    {
        if (self::$depsStack === [] || $deps === []) {
            return;
        }
        $top = array_key_last(self::$depsStack);
        self::$depsStack[$top] = array_merge(self::$depsStack[$top], $deps);
    }

    /**
     * If the cache for $sourceFile is still valid, return its tracked deps
     * (so the caller can bubble them into the parent compile frame). Returns
     * null when the cache is missing, was produced by a different compiler
     * version, or any tracked dependency has a stale mtime.
     *
     * Single-pass: replaces the previous `isFresh() + depsFrom()` two-call
     * pattern that opened and parsed the cache header twice on every hit.
     *
     * @return array<string,int>|null
     */
    public static function freshDeps(string $cacheFile, string $sourceFile): ?array
    {
        if (!is_file($cacheFile) || !is_file($sourceFile)) {
            return null;
        }
        $header = self::readHeader($cacheFile);
        if ($header === null || $header['version'] !== self::VERSION) {
            return null;
        }
        $deps = $header['deps'];
        if (($deps[$sourceFile] ?? null) !== filemtime($sourceFile)) {
            return null;
        }
        foreach ($deps as $path => $mtime) {
            if (!is_file($path) || filemtime($path) !== $mtime) {
                return null;
            }
        }
        return $deps;
    }

    /** Convenience predicate over {@see freshDeps()}. */
    public static function isFresh(string $cacheFile, string $sourceFile): bool
    {
        return self::freshDeps($cacheFile, $sourceFile) !== null;
    }

    /**
     * @deprecated since the freshDeps() refactor — prefer that since it
     * reuses the freshness check's header read. Kept for callers that only
     * want deps without the freshness check.
     * @return array<string,int>|null
     */
    public static function depsFrom(string $cacheFile): ?array
    {
        $header = self::readHeader($cacheFile);
        return $header['deps'] ?? null;
    }

    /**
     * Write compiled body to its cache file atomically (rename) with a
     * header listing the compiler version and tracked deps.
     *
     * @param array<string,int> $deps
     */
    public static function write(string $cacheFile, string $sourceFile, array $deps, string $compiled): void
    {
        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0o775, true);
        }
        // Ensure source is recorded.
        if (is_file($sourceFile)) {
            $deps[$sourceFile] = filemtime($sourceFile);
        }

        $header = "<?php\n"
                . "// ghost-compiled v" . self::VERSION . "\n"
                . "// @deps:\n";
        foreach ($deps as $path => $mtime) {
            $header .= "//   {$mtime}:{$path}\n";
        }
        // Close PHP so the compiled body's own <?php blocks stand alone.
        $header .= "?>";

        $tmp = $cacheFile . '.' . bin2hex(random_bytes(4));
        file_put_contents($tmp, $header . $compiled);
        @chmod($tmp, 0o664);
        rename($tmp, $cacheFile);
    }

    /**
     * Wipe the entire compiled-views cache. Used by `php silver optimize:clear`.
     */
    public static function clear(): int
    {
        $dir = self::cacheDir();
        if (!is_dir($dir)) {
            return 0;
        }
        $removed = 0;
        foreach (glob($dir . '*.php') ?: [] as $file) {
            if (@unlink($file)) {
                $removed++;
            }
        }
        return $removed;
    }

    /** @return array{version:int,deps:array<string,int>}|null */
    private static function readHeader(string $cacheFile): ?array
    {
        $fp = @fopen($cacheFile, 'r');
        if ($fp === false) {
            return null;
        }
        try {
            $version = null;
            $deps = [];
            $inDeps = false;
            // Cap reads to avoid runaway on a corrupt file.
            for ($i = 0; $i < 4096; $i++) {
                $line = fgets($fp);
                if ($line === false) {
                    break;
                }
                $line = rtrim($line, "\r\n");
                if ($line === '?>') {
                    break;
                }
                if (preg_match('/^\/\/ ghost-compiled v(\d+)$/', $line, $m)) {
                    $version = (int) $m[1];
                    continue;
                }
                if ($line === '// @deps:') {
                    $inDeps = true;
                    continue;
                }
                if ($inDeps && str_starts_with($line, '//   ')) {
                    $entry = substr($line, 5);
                    $colon = strpos($entry, ':');
                    if ($colon === false) {
                        continue;
                    }
                    $mt   = (int) substr($entry, 0, $colon);
                    $path = substr($entry, $colon + 1);
                    if ($path !== '') {
                        $deps[$path] = $mt;
                    }
                }
            }
            if ($version === null) {
                return null;
            }
            return ['version' => $version, 'deps' => $deps];
        } finally {
            fclose($fp);
        }
    }
}

<?php
declare(strict_types=1);

namespace Silver\Core;

/**
 * Immutable runtime context — the filesystem root and URL shape of the
 * running application. Replaces the BASEPATH / URL / CURRENT_URL / ROOT
 * bootstrap constants as the source of truth; the entrypoints still
 * define those constants as BC shims derived from this object.
 *
 * Resolution:
 *  - `public/index.php` registers {@see forWeb()} (built from $_SERVER)
 *  - `silver` registers {@see forCli()}
 *  - anything else (tests, embedded use) falls back to {@see detect()},
 *    bound as the container default — it honours the legacy constants
 *    when defined and degrades to getcwd() otherwise.
 *
 * Swap per test via:
 *
 *     app()->instance(RuntimeContext::class, new RuntimeContext(...));
 */
final readonly class RuntimeContext
{
    public function __construct(
        /** Filesystem root of the project, with trailing slash. */
        public string $root,
        /** URL path prefix when the app lives in a subdirectory ('' or '/sub'). */
        public string $basePath = '',
        /** Absolute base URL: scheme://host[:port] + basePath, no trailing slash. */
        public string $url = '',
        /** Absolute URL of the current request. */
        public string $currentUrl = '',
    ) {}

    /** Shorthand for the container-resolved singleton. */
    public static function current(): self
    {
        return app(self::class);
    }

    /**
     * Build from the web SAPI environment ($_SERVER shape).
     */
    public static function forWeb(string $root, array $server): self
    {
        $prefix = preg_replace(
            '{/index\.php$}',
            '',
            str_replace('\\', '/', (string) ($server['SCRIPT_NAME'] ?? '')),
        );

        $host = 'http' . (isset($server['HTTPS']) ? 's' : '')
            . '://' . ($server['SERVER_NAME'] ?? 'localhost')
            . ((($server['SERVER_PORT'] ?? 80) != 80) ? ':' . $server['SERVER_PORT'] : '');

        return new self(
            root:       self::normalizeRoot($root),
            basePath:   $prefix,
            url:        $host . $prefix,
            currentUrl: $host . (string) ($server['REQUEST_URI'] ?? '/'),
        );
    }

    /** Build for a CLI process — paths only, no URL shape. */
    public static function forCli(string $root): self
    {
        return new self(root: self::normalizeRoot($root));
    }

    /**
     * Fallback when no entrypoint registered a context: honour the
     * legacy constants when defined (BC), else current working dir.
     */
    public static function detect(): self
    {
        return new self(
            root:       defined('ROOT') ? \ROOT : getcwd() . '/',
            basePath:   defined('BASEPATH') ? \BASEPATH : '',
            url:        defined('URL') ? \URL : '',
            currentUrl: defined('CURRENT_URL') ? \CURRENT_URL : '',
        );
    }

    private static function normalizeRoot(string $root): string
    {
        return rtrim(str_replace('\\', '/', $root), '/') . '/';
    }
}

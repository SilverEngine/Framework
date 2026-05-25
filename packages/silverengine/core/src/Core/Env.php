<?php
declare(strict_types=1);

namespace Silver\Core;

use stdClass;

final class Env
{
    private static ?stdClass $envData = null;
    private static string $name = '';

    public static function cachePath(string $root): string
    {
        return rtrim($root, '/') . '/storage/cache/config.php';
    }

    public static function construct(?string $root = null): void
    {
        $root ??= defined('ROOT') ? ROOT : getcwd() . '/';

        // Fast path: a `php silver optimize` config cache freezes the
        // fully merged config + APP_ENV into one file, skipping dotenv
        // parsing and the config/ scandir+include on every request.
        $cache = self::cachePath($root);
        if (is_file($cache)) {
            $cached = require $cache;
            self::$name    = $cached['name'];
            self::$envData = self::arrayToObject($cached['config']);
            return;
        }

        [$name, $config] = self::build($root);
        self::$name    = $name;
        self::$envData = self::arrayToObject($config);
    }

    /**
     * Build the merged config from scratch (.env + config/ + overrides).
     * Shared by the normal boot path and the optimize cache builder.
     *
     * @return array{0:string,1:array<string,mixed>}
     */
    private static function build(string $root): array
    {
        // Native .env parser — avoids loading the 29 phpdotenv vendor files
        // on every uncached boot. Same semantics as Dotenv::safeLoad() for
        // the .env shapes we use (KEY=value, optional quotes, # comments,
        // empty values). No nested expansion, no multi-line values.
        self::loadEnvFile(rtrim($root, '/') . '/.env');

        $name = $_ENV['APP_ENV'] ?? 'local';

        $config = self::readConfiguration($root);
        self::applyEnvOverrides($config);

        return [$name, $config];
    }

    /**
     * Write the config cache (used by `php silver optimize`). Returns the
     * file path. Also primes the in-process config so the running CLI
     * sees the same data.
     */
    public static function cacheConfig(?string $root = null): string
    {
        $root ??= defined('ROOT') ? ROOT : getcwd() . '/';

        [$name, $config] = self::build($root);
        self::$name    = $name;
        self::$envData = self::arrayToObject($config);

        $path = self::cachePath($root);
        @mkdir(dirname($path), 0775, true);

        file_put_contents(
            $path,
            "<?php\n\nreturn " . var_export(['name' => $name, 'config' => $config], true) . ";\n",
            LOCK_EX,
        );

        return $path;
    }

    public static function clearConfigCache(?string $root = null): bool
    {
        $root ??= defined('ROOT') ? ROOT : getcwd() . '/';
        $path = self::cachePath($root);

        return is_file($path) ? @unlink($path) : false;
    }

    public static function name(): string
    {
        return self::$name;
    }

    public static function get(string $name, mixed $default = null): mixed
    {
        $data = self::$envData;
        if ($data === null) {
            return $default;
        }

        while (true) {
            $parts = explode('.', $name, 2);
            if (isset($data->{$parts[0]})) {
                $data = $data->{$parts[0]};
            } else {
                return $default;
            }

            if (count($parts) === 1) {
                return $data;
            }
            $name = $parts[1];
        }
    }

    /**
     * Merge the framework config defaults (shipped in the core package)
     * with the application's `config/` overrides. For each config name
     * present in either layer, the app file deep-merges over the core
     * default of the same name (see {@see self::mergeConfig()}).
     */
    private static function readConfiguration(string $root): array
    {
        $coreDir = dirname(__DIR__) . '/Config/';
        $appDir  = rtrim($root, '/') . '/config/';

        $core = self::loadConfigDir($coreDir);
        $app  = self::loadConfigDir($appDir);

        $config = $core;
        foreach ($app as $name => $value) {
            $config[$name] = (isset($core[$name]) && is_array($core[$name]) && is_array($value))
                ? self::mergeConfig($core[$name], $value)
                : $value;
        }

        return $config;
    }

    /** @return array<string,mixed> filename (lowercased, no .php) => returned config */
    private static function loadConfigDir(string $dir): array
    {
        $config = [];
        if (!is_dir($dir)) {
            return $config;
        }

        foreach (scandir($dir) as $file) {
            if (!str_ends_with($file, '.php')) {
                continue;
            }
            $config[strtolower(substr($file, 0, -4))] = include $dir . $file;
        }

        return $config;
    }

    /**
     * Recursive config merge. Associative arrays merge key-by-key
     * (override wins, untouched keys inherited); a list or scalar in the
     * override replaces the base value wholesale.
     *
     * @param array<mixed> $base
     * @param array<mixed> $override
     * @return array<mixed>
     */
    public static function mergeConfig(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (
                array_key_exists($key, $base)
                && is_array($base[$key]) && is_array($value)
                && self::isAssoc($base[$key]) && self::isAssoc($value)
            ) {
                $base[$key] = self::mergeConfig($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private static function isAssoc(array $a): bool
    {
        return $a !== [] && array_keys($a) !== range(0, count($a) - 1);
    }

    /**
     * Recursively convert nested associative arrays into stdClass while
     * preserving lists as arrays. Same shape as `json_decode(json_encode($x))`
     * but ~5-10x faster — no string serialization round-trip. Called once at
     * boot to materialise the config tree consumed via `Env::get()`'s
     * object-property traversal.
     */
    private static function arrayToObject(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if ($value === [] || array_is_list($value)) {
            $out = [];
            foreach ($value as $v) {
                $out[] = self::arrayToObject($v);
            }
            return $out;
        }
        $obj = new stdClass();
        foreach ($value as $k => $v) {
            $obj->{$k} = self::arrayToObject($v);
        }
        return $obj;
    }

    /**
     * Minimal native .env loader. Replaces vlucas/phpdotenv on the boot
     * path. Populates $_ENV / $_SERVER / putenv() the same way phpdotenv
     * "immutable" mode does — never overwrites an existing entry.
     *
     * Supported syntax:
     *   - KEY=value                  (bare value, trimmed)
     *   - KEY="value with spaces"    (double quotes — \n \t \" \\ escapes)
     *   - KEY='raw value'            (single quotes — no escape interp)
     *   - export KEY=value           (export prefix stripped)
     *   - # full-line comment
     *   - KEY=value # inline comment (unquoted only; quoted values keep #)
     *
     * Not supported (spike): ${VAR} expansion, multi-line values.
     */
    private static function loadEnvFile(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        $contents = @file_get_contents($path);
        if ($contents === false || $contents === '') {
            return;
        }

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = ltrim(substr($line, 7));
            }

            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = rtrim(substr($line, 0, $eq));
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $key)) {
                continue;
            }
            $raw = ltrim(substr($line, $eq + 1));

            // Quoted value
            if ($raw !== '' && ($raw[0] === '"' || $raw[0] === "'")) {
                $quote = $raw[0];
                $len = strlen($raw);
                $i = 1;
                if ($quote === '"') {
                    while ($i < $len) {
                        if ($raw[$i] === '\\' && $i + 1 < $len) { $i += 2; continue; }
                        if ($raw[$i] === '"') break;
                        $i++;
                    }
                } else {
                    while ($i < $len && $raw[$i] !== "'") { $i++; }
                }
                $body = substr($raw, 1, $i - 1);
                $value = $quote === '"'
                    ? strtr($body, ['\\n' => "\n", '\\t' => "\t", '\\"' => '"', '\\\\' => '\\'])
                    : $body;
            } else {
                // Unquoted — strip optional " #..." inline comment, then trim.
                if (($hash = strpos($raw, ' #')) !== false) {
                    $raw = substr($raw, 0, $hash);
                }
                $value = rtrim($raw);
            }

            // Immutable: existing values win, matching phpdotenv->safeLoad().
            if (!array_key_exists($key, $_ENV))    { $_ENV[$key]    = $value; }
            if (!array_key_exists($key, $_SERVER)) { $_SERVER[$key] = $value; }
            if (getenv($key) === false)            { putenv("{$key}={$value}"); }
        }
    }

    private static function applyEnvOverrides(array &$config): void
    {
        // Top-level overrides from .env
        if (isset($_ENV['APP_DEBUG'])) {
            $config['debug'] = filter_var($_ENV['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN);
        }

        // Config files now use env() directly for DB, mail, app_key etc.
        // No hardcoded overrides needed — single source of truth in config/*.php
    }

}

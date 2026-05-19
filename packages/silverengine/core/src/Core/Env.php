<?php
declare(strict_types=1);

namespace Silver\Core;

use Dotenv\Dotenv;
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
            self::$envData = json_decode(json_encode($cached['config']));
            return;
        }

        [$name, $config] = self::build($root);
        self::$name    = $name;
        self::$envData = json_decode(json_encode($config));
    }

    /**
     * Build the merged config from scratch (.env + config/ + overrides).
     * Shared by the normal boot path and the optimize cache builder.
     *
     * @return array{0:string,1:array<string,mixed>}
     */
    private static function build(string $root): array
    {
        $dotenv = Dotenv::createImmutable(rtrim($root, '/'));
        $dotenv->safeLoad();

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
        self::$envData = json_decode(json_encode($config));

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

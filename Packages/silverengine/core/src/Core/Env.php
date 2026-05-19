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
        return rtrim($root, '/') . '/Storage/cache/config.php';
    }

    public static function construct(?string $root = null): void
    {
        $root ??= defined('ROOT') ? ROOT : getcwd() . '/';

        // Fast path: a `php silver optimize` config cache freezes the
        // fully merged config + APP_ENV into one file, skipping dotenv
        // parsing and the Config/ scandir+include on every request.
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
     * Build the merged config from scratch (.env + Config/ + overrides).
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

    private static function readConfiguration(string $root): array
    {
        $config = [];
        $configDir = $root . 'Config/';

        if (!is_dir($configDir)) {
            return $config;
        }

        foreach (scandir($configDir) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $name = strtolower(substr($file, 0, -4));
            $config[$name] = include $configDir . $file;
        }

        return $config;
    }

    private static function applyEnvOverrides(array &$config): void
    {
        // Top-level overrides from .env
        if (isset($_ENV['APP_DEBUG'])) {
            $config['debug'] = filter_var($_ENV['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN);
        }

        // Config files now use env() directly for DB, mail, app_key etc.
        // No hardcoded overrides needed — single source of truth in Config/*.php
    }

}

<?php
declare(strict_types=1);

namespace Silver\Core;

use Dotenv\Dotenv;
use stdClass;

final class Env
{
    private static ?stdClass $envData = null;
    private static string $name = '';

    public static function construct(?string $root = null): void
    {
        $root ??= defined('ROOT') ? ROOT : getcwd() . '/';

        // Load .env file via vlucas/phpdotenv
        $dotenv = Dotenv::createImmutable(rtrim($root, '/'));
        $dotenv->safeLoad();

        self::$name = $_ENV['APP_ENV'] ?? 'local';

        $config = self::readConfiguration($root);

        // Overlay env vars onto config where applicable
        self::applyEnvOverrides($config);

        self::$envData = json_decode(json_encode($config));
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
        // Map .env keys to config structure
        if (isset($_ENV['APP_DEBUG'])) {
            $config['debug'] = filter_var($_ENV['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($_ENV['APP_KEY'])) {
            $config['app_key'] = $_ENV['APP_KEY'];
        }

        // Database overrides
        if (isset($_ENV['DB_DRIVER'])) {
            $config['databases'] ??= ['on' => true];
            $config['databases']['on'] = true;
            $config['databases']['default'] = $_ENV['DB_CONNECTION'] ?? 'local';
            $config['databases']['local'] = [
                'service'       => true,
                'driver'        => $_ENV['DB_DRIVER'] ?? 'sqlite',
                'database'      => $_ENV['DB_DATABASE'] ?? 'Database/db.sqlite',
                'hostname'      => $_ENV['DB_HOST'] ?? 'localhost',
                'port'          => $_ENV['DB_PORT'] ?? '3306',
                'username'      => $_ENV['DB_USERNAME'] ?? '',
                'password'      => $_ENV['DB_PASSWORD'] ?? '',
                'basename'      => $_ENV['DB_DATABASE'] ?? '',
                'limit_request' => (int) ($_ENV['DB_LIMIT_REQUEST'] ?? 25),
            ];
        }

        // Mail overrides
        if (isset($_ENV['MAIL_SERVICE'])) {
            $config['mail'] = [
                'service' => filter_var($_ENV['MAIL_SERVICE'], FILTER_VALIDATE_BOOLEAN),
                'email'   => $_ENV['MAIL_FROM_ADDRESS'] ?? '',
                'name'    => $_ENV['MAIL_FROM_NAME'] ?? '',
            ];
        }
    }

}

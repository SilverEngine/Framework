<?php
declare(strict_types=1);

namespace Silver\Core;

/**
 * Loads config files from the application's `config/` directory.
 *
 * Resolved as a singleton through the container; the
 * {@see \Silver\Support\Facades\Config} facade provides static
 * access for call sites that don't (or can't) take a constructor
 * dependency. Bootstrap-time config merging still lives in {@see Env}.
 */
final class Config
{
    public function get(string $name): object
    {
        $config = [];
        $path = ROOT . "/config/{$name}.php";

        if (is_file($path)) {
            $config[$name] = (object) include $path;
        }

        return (object) $config;
    }

    public function app(): object
    {
        $path = ROOT . '/config/App.php';

        if (is_file($path)) {
            return (object) include $path;
        }

        return (object) [];
    }
}

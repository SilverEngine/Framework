<?php
declare(strict_types=1);

namespace Silver\Core;

final class Config
{
    public static function get(string $name): object
    {
        $config = [];
        $path = ROOT . "/config/{$name}.php";

        if (is_file($path)) {
            $config[$name] = (object) include $path;
        }

        return (object) $config;
    }

    public static function app(): object
    {
        $path = ROOT . '/config/App.php';

        if (is_file($path)) {
            return (object) include $path;
        }

        return (object) [];
    }
}

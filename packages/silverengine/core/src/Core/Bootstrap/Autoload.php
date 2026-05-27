<?php
declare(strict_types=1);

require_once __DIR__ . '/../Env.php';

use Silver\Core\Env;

spl_autoload_register(function (string $alias): void {
    // Read from config/Autoload.php — the prefix→directory mapping.
    // Falls back to the legacy `providers` key for backwards compatibility
    // with installs that haven't been migrated yet.
    $mapping = Env::get('autoload', null) ?? Env::get('providers', []);

    foreach ($mapping as $prefix => $path) {
        if (str_starts_with($alias, $prefix)) {
            $class = substr($alias, strlen($prefix));
            $classPath = str_replace('\\', '/', $class) . '.php';
            $fullPath = ROOT . $path . $classPath;

            if (file_exists($fullPath)) {
                include_once $fullPath;
                return;
            }
        }
    }
});

if (file_exists($composer = ROOT . 'vendor/autoload.php')) {
    include $composer;
}

<?php
declare(strict_types=1);

namespace Silver\Core;

use Silver\Core\Contracts\InstanceInterface;

class App implements InstanceInterface
{
    private static ?self $current = null;
    private string $path = 'App/';
    private Container $instances;

    public function __construct()
    {
        $this->instances = new Container();
    }

    public function instances(): Container
    {
        return $this->instances;
    }

    public function register(object ...$instances): mixed
    {
        $last = null;
        foreach ($instances as $instance) {
            if (is_array($instance)) {
                foreach ($instance as $name => $inst) {
                    $last = is_numeric($name)
                        ? $this->instances->register($inst)
                        : $this->instances->registerNamed($name, $inst);
                }
            } else {
                $last = $this->instances->register($instance);
            }
        }
        return $last;
    }

    public function path(string $path = ''): string
    {
        return $this->path . $path;
    }

    public function systemPath(string $path = ''): string
    {
        return 'Packages/silverengine/core/src/App/' . $path;
    }

    public function find(string $path): ?string
    {
        $target = $this->path($path);
        if (file_exists($target)) {
            return $target;
        }

        $target = $this->systemPath($path);
        if (file_exists($target)) {
            return $target;
        }

        return null;
    }

    public static function instance(): static
    {
        if (self::$current === null) {
            self::$current = new static();
        }
        return self::$current;
    }
}

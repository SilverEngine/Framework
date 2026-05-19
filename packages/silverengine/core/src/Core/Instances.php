<?php
declare(strict_types=1);

namespace Silver\Core;

use Silver\Exception\Exception;

final class Instances
{
    private array $container = [];

    public function register(object $instance, bool $force = false): object
    {
        $class = get_class($instance);
        return $this->registerNamed($class, $instance, $force);
    }

    public function registerNamed(string $key, mixed $value, bool $force = false): mixed
    {
        if (!isset($this->container[$key]) || $force) {
            $this->container[$key] = $value;
        } else {
            throw new Exception("Instance of $key already exists.");
        }
        return $value;
    }

    public function get(string $type): mixed
    {
        return $this->container[$type] ?? null;
    }

    public function getAll(): array
    {
        return $this->container;
    }
}

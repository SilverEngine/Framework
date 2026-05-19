<?php

declare(strict_types=1);

namespace Silver\Core;

use Silver\Exception\Exception;
use Closure;
use ReflectionClass;
use ReflectionNamedType;

/**
 * The IoC container. A strict superset of the old {@see Instances}
 * registry — `register()`/`registerNamed()`/`get()`/`getAll()` keep
 * identical semantics (so `App::instances()` callers and the pinned
 * contract are unchanged: `get()` is registry-only and returns null on
 * a miss, never autowires) — plus real container features:
 *
 *  - `bind()`     — map an abstract (interface/alias) to a concrete
 *                   class name or factory Closure (fresh each resolve)
 *  - `singleton()`— same, but the resolved instance is cached
 *  - `instance()` — register a pre-built object for an abstract
 *  - `make()`     — resolve with recursive constructor autowiring
 *
 * Autowiring lives only in `make()`; `get()` deliberately does not
 * autowire so legacy behaviour is byte-for-byte preserved.
 */
final class Container
{
    /** @var array<string,mixed> registry (legacy Instances store) */
    private array $container = [];

    /** @var array<string,array{concrete:Closure|string,shared:bool}> */
    private array $bindings = [];

    /** @var array<string,mixed> resolved singleton cache */
    private array $shared = [];

    // ---- Legacy Instances surface (unchanged semantics) ----------------

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

    /** @return array<string,mixed> */
    public function getAll(): array
    {
        return $this->container;
    }

    // ---- Container features --------------------------------------------

    public function bind(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'shared'   => false,
        ];
    }

    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'shared'   => true,
        ];
    }

    public function instance(string $abstract, object $object): object
    {
        $this->shared[$abstract] = $object;
        return $object;
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract])
            || isset($this->shared[$abstract])
            || isset($this->container[$abstract])
            || class_exists($abstract);
    }

    /**
     * Resolve an abstract: cached singleton → binding → registered
     * instance → reflective constructor autowiring.
     *
     * @param array<string,mixed> $params explicit constructor overrides
     * @throws Exception when a dependency cannot be resolved
     */
    public function make(string $abstract, array $params = []): mixed
    {
        if (isset($this->shared[$abstract])) {
            return $this->shared[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $binding  = $this->bindings[$abstract];
            $concrete = $binding['concrete'];

            $object = $concrete instanceof Closure
                ? $concrete($this, $params)
                : $this->make($concrete, $params);

            if ($binding['shared']) {
                $this->shared[$abstract] = $object;
            }

            return $object;
        }

        if (array_key_exists($abstract, $this->container)) {
            return $this->container[$abstract];
        }

        return $this->build($abstract, $params);
    }

    /**
     * @param array<string,mixed> $params
     * @throws Exception
     */
    private function build(string $class, array $params): object
    {
        if (!class_exists($class)) {
            throw new Exception("Container: cannot resolve '$class'.");
        }

        $ref = new ReflectionClass($class);

        if (!$ref->isInstantiable()) {
            throw new Exception("Container: '$class' is not instantiable.");
        }

        $ctor = $ref->getConstructor();
        if ($ctor === null) {
            return new $class();
        }

        $args = [];
        foreach ($ctor->getParameters() as $param) {
            $pname = $param->getName();

            if (array_key_exists($pname, $params)) {
                $args[] = $params[$pname];
                continue;
            }

            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->make($type->getName());
                continue;
            }

            if ($param->isOptional()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            throw new Exception("Container: unable to resolve \${$pname} for '$class'.");
        }

        return $ref->newInstanceArgs($args);
    }
}

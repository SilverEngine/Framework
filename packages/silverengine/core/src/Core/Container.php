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

    /** @var array<string, list<Closure>> abstract => list of decorators */
    private array $extenders = [];

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

    /**
     * Decorate every resolution of $abstract: $decorator receives the
     * already-built instance plus the container and returns the
     * replacement (typically a wrapper). Extenders run in registration
     * order and compose — multiple extends() on the same abstract chain.
     */
    public function extend(string $abstract, Closure $decorator): void
    {
        $this->extenders[$abstract][] = $decorator;
        // Bust the singleton cache so the next make() rebuilds and re-wraps.
        unset($this->shared[$abstract]);
    }

    /**
     * Register a `before` hook on $abstract::$method. Thin delegate to
     * {@see Hook::before()} on the application's Hook singleton so users
     * can wire everything through the container they already hold a
     * reference to.
     */
    public function before(string $abstract, string $method, Closure $cb): void
    {
        $this->hook()->before($abstract, $method, $cb);
    }

    public function after(string $abstract, string $method, Closure $cb): void
    {
        $this->hook()->after($abstract, $method, $cb);
    }

    public function afterDeferred(string $abstract, string $method, Closure $cb): void
    {
        $this->hook()->afterDeferred($abstract, $method, $cb);
    }

    private ?Hook $hookRef = null;

    private function hook(): Hook
    {
        // Resolve from the app singleton so a fresh `new Container()` in a
        // test shares the same Hook the Hookable trait reads from.
        return $this->hookRef ??= App::instance()->instances()->make(Hook::class);
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

            if ($concrete instanceof Closure) {
                $object = $concrete($this, $params);
            } elseif ($concrete === $abstract) {
                // singleton($abstract) with no explicit concrete — autowire
                // directly via build() to avoid infinite recursion on the
                // same binding.
                $object = $this->build($abstract, $params);
            } else {
                $object = $this->make($concrete, $params);
            }

            $object = $this->applyExtenders($abstract, $object);

            if ($binding['shared']) {
                $this->shared[$abstract] = $object;
            }

            return $object;
        }

        if (array_key_exists($abstract, $this->container)) {
            return $this->container[$abstract];
        }

        return $this->applyExtenders($abstract, $this->build($abstract, $params));
    }

    private function applyExtenders(string $abstract, mixed $instance): mixed
    {
        if (!is_object($instance) || !isset($this->extenders[$abstract])) {
            return $instance;
        }
        foreach ($this->extenders[$abstract] as $decorator) {
            $instance = $decorator($instance, $this);
        }
        return $instance;
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

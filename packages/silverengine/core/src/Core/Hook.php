<?php
declare(strict_types=1);

namespace Silver\Core;

use Closure;

/**
 * Process-wide hook registry, resolved as a singleton through the
 * container. Services that use the {@see \Silver\Concerns\Hookable}
 * trait call {@see self::intercept()} at dispatch points; user code
 * registers callbacks against a class name OR any of its parents /
 * implemented interfaces via {@see self::before()} /
 * {@see self::after()} / {@see self::afterDeferred()}.
 *
 * Hooks compose with decorators registered on the container via
 * {@see Container::extend()}: decorators change *what* a service does
 * (structural), hooks observe or mutate *individual calls* (behavioural).
 * Use decorators to swap or wrap an implementation; use hooks for
 * cross-cutting concerns like logging, metrics, validation.
 *
 * Deferred hooks run after the response is sent — see Kernel::run().
 */
final class Hook
{
    /** @var array<string, array<string, list<Closure>>> [$abstract][$method] = [...callbacks] */
    private array $before = [];

    /** @var array<string, array<string, list<Closure>>> */
    private array $after = [];

    /** @var list<Closure> queued zero-arg flush callbacks (closure captures its own context) */
    private array $deferred = [];

    /** @var array<class-string, list<string>> cached keysFor() lookups, by concrete class */
    private array $keyCache = [];

    public function before(string $abstract, string $method, Closure $cb): void
    {
        $this->before[$abstract][$method][] = $cb;
    }

    public function after(string $abstract, string $method, Closure $cb): void
    {
        $this->after[$abstract][$method][] = $cb;
    }

    /**
     * Same shape as {@see self::after()} but the callback is held in the
     * deferred queue and runs in {@see self::runDeferred()} (called by the
     * Kernel after Response::send()). The request is already on the wire
     * by then — use for logging, metrics, audit writes, anything the user
     * doesn't need to wait for.
     */
    public function afterDeferred(string $abstract, string $method, Closure $cb): void
    {
        $this->after[$abstract][$method][] = function (object $self, array $args, mixed $result) use ($cb): mixed {
            $this->deferred[] = static fn () => $cb($self, $args, $result);
            return $result;
        };
    }

    /**
     * Intercept a single method call on $self. Fires `before` hooks (which
     * may mutate $args or throw to short-circuit), invokes $inner, then
     * fires `after` hooks (which may transform the result).
     *
     * @param array<int,mixed> $args
     */
    public function intercept(object $self, string $method, array $args, Closure $inner): mixed
    {
        $keys = $this->keysFor($self);

        // before — callbacks may return a (partial) replacement args array.
        // A shorter array pads from the original so a hook only interested
        // in the first arg can `return [$newPath]` without enumerating the rest.
        foreach ($keys as $key) {
            foreach ($this->before[$key][$method] ?? [] as $cb) {
                $ret = $cb($self, $args);
                if (is_array($ret)) {
                    $ret = array_values($ret);
                    for ($i = count($ret), $n = count($args); $i < $n; $i++) {
                        $ret[$i] = $args[$i];
                    }
                    $args = $ret;
                }
            }
        }

        $result = $inner(...$args);

        // after — callbacks may return a new $result
        foreach ($keys as $key) {
            foreach ($this->after[$key][$method] ?? [] as $cb) {
                $ret = $cb($self, $args, $result);
                if ($ret !== null) {
                    $result = $ret;
                }
            }
        }

        return $result;
    }

    public function runDeferred(): void
    {
        // Snapshot then clear so callbacks that themselves defer don't recurse.
        $queue = $this->deferred;
        $this->deferred = [];
        foreach ($queue as $cb) {
            try {
                $cb();
            } catch (\Throwable) {
                // Deferred work is fire-and-forget; never bubble after send().
            }
        }
    }

    public function reset(): void
    {
        $this->before = [];
        $this->after = [];
        $this->deferred = [];
        $this->keyCache = [];
    }

    /** @return list<string> class + parents + interfaces, in resolution order */
    private function keysFor(object $self): array
    {
        $class = $self::class;
        return $this->keyCache[$class] ??= array_values(array_unique(array_merge(
            [$class],
            class_parents($self) ?: [],
            class_implements($self) ?: [],
        )));
    }
}

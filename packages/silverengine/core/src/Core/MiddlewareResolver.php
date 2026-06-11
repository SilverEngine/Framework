<?php
declare(strict_types=1);

namespace Silver\Core;

use ReflectionClass;
use ReflectionNamedType;
use Silver\Core\Contracts\MiddlewareInterface;
use Silver\Exception\Exception;

/**
 * Turns route middleware specs into constructed middleware instances.
 *
 * A spec entry is one of:
 *   - an alias from config `middleware.aliases`   ('auth')
 *   - an alias with params                        ('throttle:60,1')
 *   - a group name from `middleware.groups`       ('web') — expands
 *     recursively, members may themselves be groups
 *   - a middleware class FQN                      (Throttle::class)
 *
 * Params are positional, mapped onto the class's builtin-typed
 * constructor parameters in order (cast to the declared scalar type);
 * class-typed constructor deps still autowire through the container.
 */
final readonly class MiddlewareResolver
{
    public function __construct(
        /** @var array<string, class-string> */
        private array     $aliases,
        /** @var array<string, list<string>> */
        private array     $groups,
        private Container $container,
    ) {}

    /**
     * @param  list<string> $spec
     * @return list<MiddlewareInterface>
     */
    public function resolve(array $spec): array
    {
        $out = [];
        foreach ($spec as $entry) {
            $this->resolveEntry($entry, $out, []);
        }
        return $out;
    }

    /**
     * @param list<MiddlewareInterface> $out
     * @param list<string>              $expanding group names currently being expanded (cycle guard)
     */
    private function resolveEntry(string $entry, array &$out, array $expanding): void
    {
        $params = [];
        $name   = $entry;
        if (str_contains($entry, ':')) {
            [$name, $paramString] = explode(':', $entry, 2);
            $params = $paramString === '' ? [] : explode(',', $paramString);
        }

        if ($params === [] && isset($this->groups[$name])) {
            if (in_array($name, $expanding, true)) {
                throw new Exception("Middleware group '{$name}' expands itself (cycle).");
            }
            foreach ($this->groups[$name] as $member) {
                $this->resolveEntry($member, $out, [...$expanding, $name]);
            }
            return;
        }

        $class = $this->aliases[$name]
            ?? (class_exists($name) ? $name : throw new Exception(
                "Unknown middleware '{$name}' — not an alias, group, or class.",
            ));

        $instance = $this->container->make($class, $this->mapParams($class, $params));
        if (!$instance instanceof MiddlewareInterface) {
            throw new Exception("Middleware '{$class}' must implement " . MiddlewareInterface::class . '.');
        }
        $out[] = $instance;
    }

    /**
     * Positional 'throttle:60,1' params → name-keyed constructor
     * overrides, cast to the declared builtin type. Class-typed ctor
     * params are skipped so the container autowires them.
     *
     * @param  list<string> $params
     * @return array<string, mixed>
     */
    private function mapParams(string $class, array $params): array
    {
        if ($params === []) {
            return [];
        }

        $ctor = new ReflectionClass($class)->getConstructor()
            ?? throw new Exception("Middleware '{$class}' takes no constructor params, got '" . implode(',', $params) . "'.");

        $named = [];
        foreach ($ctor->getParameters() as $p) {
            if ($params === []) {
                break;
            }
            $type = $p->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                continue; // service dep — container's job
            }
            $named[$p->getName()] = $this->castParam(array_shift($params), $type);
        }

        if ($params !== []) {
            throw new Exception(
                "Middleware '{$class}' has fewer scalar constructor params than given ('" . implode(',', $params) . "').",
            );
        }

        return $named;
    }

    private function castParam(string $value, ?\ReflectionType $type): mixed
    {
        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }
        return match ($type->getName()) {
            'int'   => (int) $value,
            'float' => (float) $value,
            'bool'  => filter_var($value, FILTER_VALIDATE_BOOL),
            default => $value,
        };
    }
}

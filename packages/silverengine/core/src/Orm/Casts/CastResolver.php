<?php
declare(strict_types=1);

namespace Silver\Orm\Casts;

use BackedEnum;
use DateTimeImmutable;
use ReflectionEnum;
use ReflectionNamedType;
use Silver\Orm\Attributes\Cast;

/**
 * Maps an attribute spec ({@see Cast} attribute, or a native PHP
 * property type) to a concrete {@see CastsAttribute} instance.
 *
 * Stateless lookup — instances are memoized internally so repeated
 * hydrations don't re-allocate the same cast object.
 */
final class CastResolver
{
    /** @var array<string, CastsAttribute> */
    private static array $cache = [];

    public static function resolveExplicit(Cast $attr): CastsAttribute
    {
        $key = $attr->type . ':' . md5(serialize($attr->args));
        return self::$cache[$key] ??= self::build($attr->type, $attr->args);
    }

    /**
     * Infer a cast from the PHP property type. Returns null when no
     * automatic cast applies (string properties, untyped props, etc.).
     */
    public static function resolveFromType(?ReflectionNamedType $type): ?CastsAttribute
    {
        if ($type === null) {
            return null;
        }
        $name = $type->getName();
        if ($name === 'string') {
            return null;
        }
        if ($name === 'bool')   { return self::cached('bool'); }
        if ($name === 'int')    { return self::cached('int'); }
        if ($name === 'float')  { return self::cached('float'); }
        if ($name === 'array')  { return self::cached('array'); }
        if ($name === DateTimeImmutable::class) {
            return self::cached('datetime');
        }
        if (class_exists($name) && self::isBackedEnum($name)) {
            return self::cached('enum:' . $name, fn () => new EnumCast($name));
        }
        return null;
    }

    private static function cached(string $key, ?callable $factory = null): CastsAttribute
    {
        return self::$cache[$key] ??= ($factory !== null ? $factory() : self::build($key, []));
    }

    /** @param array<int, mixed> $args */
    private static function build(string $type, array $args): CastsAttribute
    {
        return match (strtolower($type)) {
            'json'      => new JsonCast(),
            'array'     => new ArrayCast(),
            'datetime'  => new DateTimeCast(),
            'date'      => new DateCast(),
            'bool'      => new BoolCast(),
            'int'       => new IntCast(),
            'float'     => new FloatCast(),
            'string'    => new StringCast(),
            'encrypted' => new EncryptedCast(...$args),
            default     => self::buildClass($type, $args),
        };
    }

    /** @param array<int, mixed> $args */
    private static function buildClass(string $type, array $args): CastsAttribute
    {
        if (!class_exists($type)) {
            throw new \LogicException("Unknown cast '{$type}'. Built-in shorthands: json, array, datetime, date, bool, int, float, string, encrypted.");
        }
        if (self::isBackedEnum($type)) {
            return new EnumCast($type);
        }
        $instance = new $type(...$args);
        if (!$instance instanceof CastsAttribute) {
            throw new \LogicException(
                "Cast '{$type}' must implement " . CastsAttribute::class . '.'
            );
        }
        return $instance;
    }

    private static function isBackedEnum(string $class): bool
    {
        if (!enum_exists($class)) {
            return false;
        }
        return (new ReflectionEnum($class))->isBacked();
    }

    /** Test helper: drop the memoization cache. */
    public static function flush(): void { self::$cache = []; }
}

<?php
declare(strict_types=1);

namespace Silver\Orm\Model;

use ReflectionClass;

/**
 * Hydrate DB rows into typed Model instances. Builds the instance
 * via ReflectionClass::newInstanceWithoutConstructor() so user
 * models with required constructor args (rare on AR models) still
 * load cleanly.
 *
 * Casts run on read; the resulting PHP-side value is assigned to
 * the typed property. Snapshot of the DB-shaped original row is
 * stored on the instance for dirty-diff at save().
 */
final class Hydrator
{
    /** @var array<class-string, ReflectionClass<object>> */
    private static array $reflectionCache = [];

    /**
     * @template T of Model
     * @param  class-string<T>          $class
     * @param  array<string, mixed>     $row
     * @return T
     */
    public static function hydrate(string $class, array $row): Model
    {
        $meta = AttributeRegistry::for($class);
        $rc   = self::$reflectionCache[$class] ??= new ReflectionClass($class);

        /** @var T $instance */
        $instance = $rc->newInstanceWithoutConstructor();

        foreach ($row as $column => $value) {
            $prop = $meta->properties[$column] ?? null;
            if ($prop === null) {
                // Unknown column — likely a join result. Stash on the
                // instance's $extras bag so accessors can still reach it.
                $instance->setExtra($column, $value);
                continue;
            }

            $assigned = $prop->cast !== null ? $prop->cast->get($value) : $value;
            // Skip assigning null to a non-nullable typed property to
            // avoid PHP TypeError; user's NOT NULL DB schema should
            // prevent this in practice but a defensive skip keeps the
            // hydrator from killing the request.
            if ($assigned === null && $prop->type !== null && !$prop->allowsNull) {
                continue;
            }
            $instance->{$column} = $assigned;
        }

        $instance->setOriginal($row);
        $instance->markExists(true);
        return $instance;
    }
}

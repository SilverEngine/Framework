<?php
declare(strict_types=1);

namespace Silver\Support;

use Silver\Core\App;

abstract class Facade
{
    abstract protected static function getClass(): string;

    /**
     * Resolve the proxied target through the application container so a
     * facade and the rest of the framework share one instance — this
     * replaces the old private per-class `$objects` cache (the third,
     * separate singleton mechanism). A container-registered class (e.g.
     * the request-scoped Request/Response) returns that registered
     * object; otherwise it is built once and registered, preserving the
     * historical lazy-singleton behaviour.
     */
    public static function __callStatic(string $fname, array $args): mixed
    {
        $class     = static::getClass();
        $container = App::instance()->instances();

        $object = $container->get($class);
        if ($object === null) {
            $object = $container->make($class);
            $container->registerNamed($class, $object, true);
        }

        return $object->$fname(...$args);
    }
}

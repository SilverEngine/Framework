<?php
declare(strict_types=1);

namespace Silver\Support;

abstract class Facade
{
    private static array $objects = [];

    abstract protected static function getClass(): string;

    public static function __callStatic(string $fname, array $args): mixed
    {
        $class = static::getClass();

        $object = self::$objects[$class] ??= new $class();

        return $object->$fname(...$args);
    }
}

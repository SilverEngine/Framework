<?php
declare(strict_types=1);

namespace Silver\Core;

use Silver\Exception\Exception;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;

final class DI
{
    public static function call(callable|array $callable, array $vars = []): mixed
    {
        $vars = self::prepareVars($vars);

        if (is_array($callable)) {
            [$obj, $method] = $callable;
            $refMethod = new ReflectionMethod($obj, $method);
            $parameters = $refMethod->getParameters();
        } else {
            $refFn = new ReflectionFunction($callable);
            $parameters = $refFn->getParameters();
        }

        $args = [];
        foreach ($parameters as $param) {
            $type = $param->getType();
            $name = ($type instanceof ReflectionNamedType && !$type->isBuiltin())
                ? $type->getName()
                : $param->getName();

            if (array_key_exists($name, $vars)) {
                $args[] = $vars[$name];
            } elseif ($param->isOptional()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new Exception("DI: Unable to inject parameter $name");
            }
        }

        if (is_array($callable)) {
            [$obj, $method] = $callable;
            return $obj->$method(...$args);
        }

        return $callable(...$args);
    }

    private static function prepareVars(array $vars): array
    {
        $prepared = [];
        foreach ($vars as $key => $value) {
            if (is_numeric($key)) {
                if (is_object($value)) {
                    $prepared['\\' . get_class($value)] = $value;
                } else {
                    throw new Exception("DI: Non object value must have specified name.");
                }
            } else {
                $prepared[$key] = $value;
            }
        }
        return $prepared;
    }
}

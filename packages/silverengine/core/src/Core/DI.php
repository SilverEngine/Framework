<?php
declare(strict_types=1);

namespace Silver\Core;

use Silver\Exception\Exception;
use Silver\Exception\NotFoundException;
use Silver\Http\Contracts\ValidatesData;
use Silver\Orm\Model\Model;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Argument injector for callables — resolves a function/method's
 * parameters from a name- or type-keyed array. Resolved as a singleton
 * through the container; used by {@see Kernel} to invoke controller
 * actions with the matched route variables.
 *
 * Resolution order for each parameter:
 *   1. Explicit value in $vars keyed by name (route variables) or by
 *      class FQN (pre-registered services from the container).
 *   2. Class-typed params not in $vars → autowired via Container::make()
 *      so request-bound DTOs like {@see \Silver\Http\FormRequest} can be
 *      type-hinted on actions without manual registration.
 *   3. Optional parameter default.
 *   4. Fail loudly.
 *
 * If an autowired (or pre-supplied) instance implements
 * {@see ValidatesData}, its validateResolved() runs immediately after
 * resolution — so a controller never sees an unvalidated FormRequest.
 */
final class DI
{
    public function call(callable|array $callable, array $vars = []): mixed
    {
        $vars = $this->prepareVars($vars);

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
            $type      = $param->getType();
            $isClass   = $type instanceof ReflectionNamedType && !$type->isBuiltin();
            $className = $isClass ? $type->getName() : null;
            $key       = $isClass ? $className : $param->getName();

            if (array_key_exists($key, $vars)) {
                $value = $isClass ? $vars[$key] : $this->coerceScalar($vars[$key], $type);
            } elseif ($isClass
                && is_subclass_of($className, Model::class)
                && array_key_exists($param->getName(), $vars)
                && is_scalar($vars[$param->getName()])) {
                // Route model binding: `User $user` + route var 'user' ⇒
                // User::find($id), 404 (or null for nullable params) on miss.
                $value = $this->resolveModelBinding($className, $vars[$param->getName()], $param);
            } elseif ($isClass) {
                // Autowire unknown class params (FormRequests, etc.) through
                // the container instead of failing — registered singletons
                // came in via $vars already.
                $value = App::instance()->instances()->make($className);
            } elseif ($param->isOptional()) {
                $value = $param->getDefaultValue();
            } else {
                throw new Exception("DI: Unable to inject parameter \${$key}");
            }

            if ($value instanceof ValidatesData) {
                $value->validateResolved();
            }

            $args[] = $value;
        }

        if (is_array($callable)) {
            [$obj, $method] = $callable;
            return $obj->$method(...$args);
        }

        return $callable(...$args);
    }

    /**
     * Route variables are matched as strings — bend them to the
     * declared scalar type so `int $id` works under strict_types.
     * Values that don't cleanly convert pass through unchanged (the
     * resulting TypeError names the real culprit).
     */
    private function coerceScalar(mixed $value, ?\ReflectionType $type): mixed
    {
        if (!is_string($value) || !$type instanceof ReflectionNamedType || !$type->isBuiltin()) {
            return $value;
        }
        return match ($type->getName()) {
            'int'   => preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : $value,
            'float' => is_numeric($value) ? (float) $value : $value,
            'bool'  => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $value,
            default => $value,
        };
    }

    /**
     * @param class-string<Model> $modelClass
     */
    private function resolveModelBinding(string $modelClass, mixed $id, ReflectionParameter $param): ?Model
    {
        $model = $modelClass::find($id);
        if ($model instanceof Model) {
            return $model;
        }
        if ($param->getType()?->allowsNull()) {
            return null;
        }
        $base = substr($modelClass, (int) strrpos($modelClass, '\\') + 1);
        throw new NotFoundException("No {$base} found for '{$id}'.");
    }

    private function prepareVars(array $vars): array
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

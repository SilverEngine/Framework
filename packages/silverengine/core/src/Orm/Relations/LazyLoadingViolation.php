<?php
declare(strict_types=1);

namespace Silver\Orm\Relations;

use RuntimeException;

final class LazyLoadingViolation extends RuntimeException
{
    public static function for(string $modelClass, string $relation): self
    {
        return new self(
            sprintf(
                'Lazy access to relation %s::%s. Eager-load it explicitly via ->with(\'%s\') '
                . 'or call %1$s::query()->...->all() and access via $instance->%2$s().',
                $modelClass,
                $relation,
                $relation,
            ),
        );
    }
}

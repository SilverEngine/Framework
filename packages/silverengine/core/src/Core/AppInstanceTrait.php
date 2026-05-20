<?php
declare(strict_types=1);

namespace Silver\Core;

trait AppInstanceTrait
{
    public static function instance(): static
    {
        return App::instance()->instances()->get(static::class);
    }
}

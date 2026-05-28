<?php
declare(strict_types=1);

namespace Silver\Support\Facades;

use Silver\Support\Facade;

final class Log extends Facade
{
    protected static function getClass(): string
    {
        return \Silver\Support\Log::class;
    }
}

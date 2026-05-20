<?php
declare(strict_types=1);

namespace Silver\Core\Bootstrap\Facades;

use Silver\Support\Facade;

final class Log extends Facade
{
    protected static function getClass(): string
    {
        return \Silver\Support\Log::class;
    }
}

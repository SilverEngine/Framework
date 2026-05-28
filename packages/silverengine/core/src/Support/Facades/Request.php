<?php
declare(strict_types=1);

namespace Silver\Support\Facades;

use Silver\Support\Facade;

final class Request extends Facade
{
    protected static function getClass(): string
    {
        return \Silver\Http\Request::class;
    }
}

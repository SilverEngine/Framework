<?php
declare(strict_types=1);

namespace Silver\Core\Contracts;

use Silver\Http\Request;
use Silver\Http\Response;
use Closure;

interface MiddlewareInterface
{
    public function execute(Request $req, Response $res, Closure $next): mixed;
}

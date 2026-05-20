<?php
declare(strict_types=1);

namespace Silver\App\Middlewares;

use Silver\Core\Contracts\MiddlewareInterface;
use Silver\Http\Request;
use Silver\Http\Response;
use Closure;

final class ApiTransform implements MiddlewareInterface
{
    public function execute(Request $req, Response $res, Closure $next): mixed
    {
        return $next();
    }
}

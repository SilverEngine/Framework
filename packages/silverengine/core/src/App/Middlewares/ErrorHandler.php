<?php
declare(strict_types=1);

namespace Silver\App\Middlewares;

use Silver\Core\Contracts\MiddlewareInterface;
use Silver\Core\ErrorHandler as Handler;
use Silver\Exception\NotFoundException;
use Silver\Http\Request;
use Silver\Http\Response;
use Closure;

final class ErrorHandler implements MiddlewareInterface
{
    public function execute(Request $req, Response $res, Closure $next): mixed
    {
        try {
            return $next();
        } catch (NotFoundException $e) {
            $res->setCode(404);
            return Handler::render($e);
        } catch (\Throwable $e) {
            $res->setCode(500);
            // Keep the original throwable as `previous` so the error
            // page shows its real class, file/line and stack trace
            // (the wrapper's own trace would be useless).
            $wrapped = new \Silver\Exception\Exception($e->getMessage(), (int) $e->getCode(), $e);
            $wrapped->setFile($e->getFile());
            $wrapped->setLine($e->getLine());
            return Handler::render($wrapped);
        }
    }
}

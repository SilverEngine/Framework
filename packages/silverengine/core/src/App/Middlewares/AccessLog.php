<?php
declare(strict_types=1);

namespace Silver\App\Middlewares;

use Silver\Core\Contracts\MiddlewareInterface;
use Silver\Http\Request;
use Silver\Http\Response;
use Closure;

final class AccessLog implements MiddlewareInterface
{
    public function execute(Request $req, Response $res, Closure $next): mixed
    {
        $path = ROOT . 'storage/Logs/' . date('Y-m-d') . '-access.log';
        $line = sprintf("%20s [%s] %s: %s\n", $req->ip(), date('Y-m-d H:i:s'), $req->method(), $req->getUri());

        $fp = fopen($path, 'a+');
        if ($fp) {
            fwrite($fp, $line);
            fclose($fp);
        }

        return $next();
    }
}

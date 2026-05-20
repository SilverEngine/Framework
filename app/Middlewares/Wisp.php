<?php
declare(strict_types=1);

namespace App\Middlewares;

use Silver\Core\Contracts\MiddlewareInterface;
use Silver\Engine\Ghost\Wisp as WispEngine;
use Silver\Http\Request;
use Silver\Http\Response;
use Closure;

/**
 * Wisp (Inertia) protocol handshake.
 *
 * - Asset version mismatch on a GET navigation -> 409 + X-Inertia-Location
 *   so the client performs a hard reload and picks up fresh assets.
 * - Redirect after a mutating verb -> 303 so the browser issues a GET.
 *
 * Registered in config/Middlewares.php after AccessLog, before Version.
 */
final class Wisp implements MiddlewareInterface
{
    public function execute(Request $req, Response $res, Closure $next): mixed
    {
        $isWisp = $req->hasHeader('X-Inertia');

        if ($isWisp && $req->method() === 'get') {
            $current = WispEngine::version();
            $client  = $req->headerValue('X-Inertia-Version');

            if ($current !== null && $client !== null && $client !== $current) {
                $res->setCode(409);
                $res->setHeader('X-Inertia-Location', defined('CURRENT_URL') ? CURRENT_URL : ($req->getUri() ?? '/'));

                return null;
            }
        }

        $ret = $next();

        if ($isWisp
            && in_array($req->method(), ['put', 'patch', 'delete'], true)
            && $res->getCode() === 302
        ) {
            $res->setCode(303);
        }

        return $ret;
    }
}

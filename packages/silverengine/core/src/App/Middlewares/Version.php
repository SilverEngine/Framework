<?php
declare(strict_types=1);

namespace Silver\App\Middlewares;

use Silver\Core\Contracts\MiddlewareInterface;
use Silver\Http\Request;
use Silver\Http\Response;
use Silver\Http\View;
use Closure;

final class Version implements MiddlewareInterface
{
    public function execute(Request $req, Response $res, Closure $next): mixed
    {
        $r = $next();

        if ($r instanceof View && file_exists('.git/HEAD')) {
            $line = file('.git/HEAD', FILE_USE_INCLUDE_PATH)[0] ?? '';
            $pos = strrpos($line, '/');
            if ($pos !== false) {
                $r->with('_branch_', trim(substr($line, $pos + 1)));
            }
        }

        return $r;
    }
}

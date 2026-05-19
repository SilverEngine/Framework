<?php

/**
 * SilverEngine  - PHP MVC framework
 *
 * @package   SilverEngine
 * @author    SilverEngine Team
 * @copyright 2015-2017
 * @license   MIT
 * @link      https://github.com/SilverEngine/Framework
 */

namespace Silver\App\Middlewares;

use Silver\Core\Contracts\MiddlewareInterface;
use Silver\Http\Request;
use Silver\Http\Response;
use Silver\Http\View;
use Closure;

class Version implements MiddlewareInterface
{
    public function execute(Request $req, Response $res, Closure $next): mixed
    {
        $r = $next();

        if ($r instanceof View) {
            if (file_exists('.git/HEAD')) {
                $line = file('.git/HEAD', FILE_USE_INCLUDE_PATH)[0];
                $pos = strrpos($line, '/');
                $branch = substr($line, $pos+1);
                $r->with('_branch_', $branch);
            }
        }
        return $r;
    }
}
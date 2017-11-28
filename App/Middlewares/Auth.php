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

namespace App\Middlewares;

use App\Facades\Api;
use Firebase\JWT\JWT;
use Silver\Core\Blueprints\MiddlewareInterface;
use Silver\Core\Env;
use Silver\Http\Request;
use Silver\Http\Response;
use Closure;
use Silver\Http\Session;

class Auth implements MiddlewareInterface
{
    // put the name to make it public
    private $unguard = [
        'unguard',
        'home',
    ];

    public function execute(Request $req, Response $res, Closure $next)
    {
        if($req->route() == null)
            return $next();

        if (array_search($req->route()->name(), $this->unguard) !== false) {

            return $next();
        }
        else{
           //put your secure code here!
            return $next();
        }
    }

    private function getHeaders()
    {
        return $this->headers;
    }

}



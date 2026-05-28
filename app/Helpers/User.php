<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Users;
use Silver\Core\Env;
use Silver\Http\Redirect;
use Silver\Http\Session;

class User
{

    public function me($demo = 'me')
    {
        return $demo;
    }

}

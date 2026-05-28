<?php

namespace App\Helpers;

use App\Models\Users;
use Silver\Core\Env;
use Silver\Http\Redirect;
use Silver\Http\Session;

class User
{

    private $user;

    public function me($demo = 'me')
    {
        return $this->user = $demo;
    }

}

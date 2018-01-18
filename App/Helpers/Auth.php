<?php

namespace App\Helpers;

/**
 * auth Helper
 */
class Auth
{
    private $status = false;
    public function isLoggedIn()
    {
       return $this->status;
    }
}

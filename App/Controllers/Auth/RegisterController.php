<?php
namespace App\Controllers\Auth;

use Silver\Core\Controller;
use Silver\Http\View;

/**
* auth.login controller
*/
class RegisterController extends Controller
{
    public function get()
    {
        return View::make('auth.register');
    }
}

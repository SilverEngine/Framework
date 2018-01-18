<?php
namespace App\Controllers\Auth;

use Silver\Core\Controller;
use Silver\Http\View;
use Silver\Database\Query;
use Silver\Http\Validator;
use Silver\Core\Bootstrap\Facades\Request;
use Silver\Facades\Auth;
use \Firebase\JWT\JWT;

/**
* auth.login controller
*/
class LoginController extends Controller
{
    public function get()
    {
        return View::make('auth.login');
    }

    public function post()
    {
        $req = (object) Request::all();

        $validator = Validator::check($req, [
           "email" => "required",
           "pasword"    => "min:6|required",
        ]);

         if(Validator::pass())
         {
             $user = User::where('email', $req->email)->where('password', md($req->password))->first();
             Session::set('user_id', $user->id);

             return Redirect::to('/');
         } else
         {
             Session::flash('error', $validator);
             return Redirect::to('/login');
         }
    }
}

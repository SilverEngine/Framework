<?php
namespace App\Controllers;

use Silver\Core\Controller;
use Silver\Http\View;

/**
 * test1 controller
 */
class Test1Controller extends Controller
{
    public function get()
    {
        echo "Welcome in test1 controller. This file is on App/Controllers/";
        //        return View::make('');
    }

    public function post()
    {
        echo 'Method: post';
    }

    public function put()
    {
        echo 'Method: put';
    }

    public function delete()
    {
        echo 'Method: delete';
    }
}

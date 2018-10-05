<?php
namespace App\Controllers;

use Silver\Core\Controller;
use Silver\Http\View;

/**
* nejc controller
*/
class NejcController extends Controller
{

    protected $name = "nejc";

    public function get()
    {
        //echo "Welcome in nejc controller. This file is on App/Controllers/";
        return View::make('nejc')->with('name', $this->name);
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

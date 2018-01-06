<?php
namespace App\Controllers;

use Silver\Core\Controller;
use Silver\Http\View;

/**
* contact controller
*/
class ContactController extends Controller
{
    public function get()
    {
        return "Welcome in contact controller. This file is on App/Controllers/";
    }

    public function post()
    {
        echo 'Methode: post';
    }

    public function put()
    {
        echo 'Methode: put';
    }

    public function delete()
    {
        echo 'Methode: delete';
    }
}

<?php
namespace App\Controllers;

use Silver\Core\Controller;
use Silver\Http\View;

/**
* blog controller
*/
class BlogController extends Controller
{
    public function get()
    {
        return View::make('blog.app');
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

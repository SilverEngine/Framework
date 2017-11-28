<?php echo '<?php'; ?>

namespace App\Controllers;

use Silver\Core\Controller;

/**
* {{{$name}}} controller
*/
class {{{ucfirst($name)}}}Controller extends Controller
{
    public function get()
    {
        return "Welcome in {{$name}} controller. This file is on App/Controllers/";
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

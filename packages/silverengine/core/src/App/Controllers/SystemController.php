<?php
declare(strict_types=1);

namespace System\App\Controllers;

use Silver\Core\Controller;
use Silver\Init\Image;

class SystemController extends Controller
{
    public function pull(): string
    {
        echo " - System update framework start<br>";
        Image::archive();
        echo " - Archive complete<br>";
        Image::pull();
        echo " - Pulled 100% complete<br>";
        Image::unzip();
        return ' - Update framework complete';
    }
}

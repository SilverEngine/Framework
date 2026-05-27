<?php

declare(strict_types=1);

namespace App\Controllers;

use Silver\Core\Controller;
use Silver\Engine\Ghost\WispResponse;

final class UsersController extends Controller
{
    public function __invoke(): WispResponse
    {
        return wisp('Users', [
            'message' => 'Scaffolded by SilverEngine.',
        ]);
    }
}

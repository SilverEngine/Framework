<?php

declare(strict_types=1);

namespace App\Controllers;

use Silver\Core\Controller;
use Silver\Core\Route;
use Silver\Http\View;
use Silver\Support\Git;

final class WelcomeController extends Controller
{
    public function __construct(private readonly Route $route) {}

    public function __invoke(): View
    {
        return View::make('demo.default')
            ->with('_branch_', Git::test())
            ->with('serverTime', date('Y-m-d H:i:s'))
            ->with('routes', count($this->route->all()));
    }
}

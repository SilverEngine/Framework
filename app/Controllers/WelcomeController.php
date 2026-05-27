<?php

declare(strict_types=1);

namespace App\Controllers;

use Silver\Core\Controller;
use Silver\Core\Env;
use Silver\Core\Route;
use Silver\Engine\Ghost\WispResponse;
use Silver\Support\Git;

final class WelcomeController extends Controller
{
    public function __construct(private readonly Route $route) {}

    public function __invoke(): WispResponse
    {
        return wisp('Scaffolder', [
            'phpVersion' => PHP_VERSION,
            'branch'     => Git::test() ?: 'detached',
            'routes'     => count($this->route->all()),
            'canScaffold' => Env::name() === 'local' && (bool) Env::get('debug'),
        ]);
    }
}

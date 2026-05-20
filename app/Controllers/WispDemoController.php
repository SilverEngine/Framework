<?php

declare(strict_types=1);

namespace App\Controllers;

use Silver\Core\Controller;
use Silver\Core\Route;
use Silver\Engine\Ghost\Wisp;
use Silver\Engine\Ghost\WispResponse;

final class WispDemoController extends Controller
{
    public function __construct(private readonly Route $route) {}

    public function __invoke(): WispResponse
    {
        $router = $this->route;
        return wisp('Welcome', [
            'appName'    => 'SilverEngine',
            'serverTime' => date('Y-m-d H:i:s'),
            'message'    => 'Wisp is running — server-driven Vue, baked into Ghost.',
            // Deferred prop: excluded from first paint, fetched after mount.
            'stats'      => Wisp::defer(fn(): array => ['routes' => count($router->all())]),
        ]);
    }
}

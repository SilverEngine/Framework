<?php
declare(strict_types=1);

namespace App;

use Silver\Core\Env;

/** @var \Silver\Core\Route $route */

// Framework self-check endpoint — available in every environment.
// Returns a JSON status envelope; 200 when healthy/degraded, 503 when down.
$route->get(
    '/heartbeat',
    \System\App\Controllers\HeartbeatController::class,
    'silver.heartbeat',
);

if (Env::get('debug')) {
    $route->get('/debug', 'Debug@index', 'debug');
}

if (Env::name() === 'local') {
    // Migrations are CLI-only now (php silver migrate / migrate:rollback /
    // migrate:fresh / migrate:status). The old web migrate routes
    // (Migrations@up/down/all) were removed in P7 alongside the
    // Silver\Database\ tree.

    // 404 dev scaffolder endpoint (POST). Gated again controller-side.
    if (Env::get('debug')) {
        $route->post(
            '/__silver/scaffold',
            \System\App\Controllers\ScaffoldController::class,
            'silver.scaffold',
        );

        // Scaffolder UI page. Mounted at the configured route — default
        // `/new`. Set `scaffolder.enabled => false` (or override `route`)
        // in config/Scaffolder.php to free this path up for your own app.
        if (Env::get('scaffolder.enabled') !== false) {
            $route->get(
                (string) (Env::get('scaffolder.route') ?: '/new'),
                \System\App\Controllers\NewController::class,
                'silver.scaffolder',
            );
        }
    }
}

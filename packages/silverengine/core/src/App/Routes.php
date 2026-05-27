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
    $route->get('/migrate/{modelName?}', 'Migrations@up', 'migrate');
    $route->get('/migrate-down/{modelName?}', 'Migrations@down', 'migrate-down');
    $route->get('/migrate-seed', 'Migrations@all', 'migrate-seed');

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

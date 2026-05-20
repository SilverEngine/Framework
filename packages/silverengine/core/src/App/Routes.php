<?php
declare(strict_types=1);

namespace App;

use Silver\Core\Env;

/** @var \Silver\Core\Route $route */

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
    }
}

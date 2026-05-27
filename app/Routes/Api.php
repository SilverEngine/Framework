<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\ApiController;

/** @var \Silver\Core\Route $route */
$route->group(['prefix' => 'api'], function () use ($route) {
    $route->get('/', ApiController::class, 'api.index');
});

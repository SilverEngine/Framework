<?php

declare(strict_types=1);

namespace App\Routes;

use Silver\Core\Route;
use App\Controllers\ApiController;

/** @var Route $route */
$route->group(['prefix' => 'api'], function () use ($route): void {
    $route->get('/', ApiController::class, 'api.index');
});

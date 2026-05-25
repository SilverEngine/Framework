<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\WelcomeController;
use App\Controllers\WispDemoController;

/** @var \Silver\Core\Route $route */
$route->get('/', WelcomeController::class, 'home', 'public');
$route->get('/wisp-demo', WispDemoController::class, 'wisp.demo', 'public');

<?php

/*
 * Route-level middleware (F2). Distinct from Middlewares.php (the
 * global priority stack): these run per route, between the global
 * stack and the controller action.
 *
 * aliases — short names usable in route definitions, with optional
 *           positional params: $route->get('/login', ..., null,
 *           ['throttle:5,60']).
 * groups  — named bundles of aliases/classes/other groups:
 *           $route->group(['middleware' => ['web']], ...).
 *
 * App config/Middleware.php deep-merges over this file.
 */
return [
    'aliases' => [
        'throttle' => Silver\Auth\Middleware\Throttle::class,
        'auth'     => Silver\Auth\Middleware\Authenticate::class,
        'guest'    => Silver\Auth\Middleware\Guest::class,
        'can'      => Silver\Auth\Middleware\Authorize::class,
        'verified' => Silver\Auth\Middleware\EnsureEmailIsVerified::class,
    ],
    'groups' => [],
];

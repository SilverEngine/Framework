<?php

/*
 * CORS — consumed by Silver\Http\Middleware\Cors. Add the middleware
 * to config/Middlewares.php (global stack, early — before routing-
 * dependent middleware so preflights short-circuit cheaply), then
 * shape the policy here. App config/Cors.php deep-merges over this.
 *
 * With `supports_credentials` the spec forbids the '*' origin — the
 * middleware echoes the caller's origin automatically in that case.
 */
return [
    // Which request paths get CORS handling at all (fnmatch patterns).
    'paths'                => ['/api/*'],

    // '*', exact origins, or fnmatch patterns ('https://*.example.com').
    'allowed_origins'      => [],

    // '*' echoes the preflight's requested method.
    'allowed_methods'      => ['*'],

    // '*' echoes the preflight's requested headers.
    'allowed_headers'      => ['*'],

    // Access-Control-Expose-Headers on actual responses.
    'exposed_headers'      => [],

    // Preflight cache lifetime in seconds (0 = header omitted).
    'max_age'              => 0,

    'supports_credentials' => false,
];

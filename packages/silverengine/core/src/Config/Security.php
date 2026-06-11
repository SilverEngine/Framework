<?php

/*
 * Security response headers — consumed by
 * Silver\Http\Middleware\SecurityHeaders. Add the middleware to
 * config/Middlewares.php; app config/Security.php deep-merges over
 * this file. Set any header key to null to omit it. Headers already
 * set on the Response (per-route CSP, say) are never overwritten.
 */
return [
    'x-frame-options'         => 'SAMEORIGIN',
    'x-content-type-options'  => 'nosniff',
    'referrer-policy'         => 'strict-origin-when-cross-origin',

    // Too app-specific to default safely — enable when ready, e.g.
    // "default-src 'self'; img-src 'self' data:".
    'content-security-policy' => null,

    // e.g. "camera=(), microphone=(), geolocation=()".
    'permissions-policy'      => null,

    // Emitted only on HTTPS requests (browsers ignore it over HTTP).
    'hsts' => [
        'enabled'            => true,
        'max-age'            => 31536000,
        'include-subdomains' => true,
        'preload'            => false,
    ],
];

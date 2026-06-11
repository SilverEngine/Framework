<?php
declare(strict_types=1);

/*
 * General cache (Silver\Support\Cache singleton, `cache()` helper).
 *
 * Drivers:
 *   array — per-request, in-memory (safe default; TTL ignored)
 *   apcu  — cross-request, single process (no-ops when APCu is absent)
 */
return [
    'driver' => env('CACHE_DRIVER', 'array'),

    // Key prefix for shared backends (APCu).
    'prefix' => env('CACHE_PREFIX', 'silver:cache:'),
];

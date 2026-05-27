<?php

/*
|--------------------------------------------------------------------------
| Service Providers
|--------------------------------------------------------------------------
| Classes implementing Silver\Core\Bootstrap\ServiceProvider that the
| Kernel constructs once per request. before() runs *before* the
| middleware pipeline + controller; after() runs after the response is
| flushed (matched, in declaration order).
|
| Use providers for kernel-level setup that doesn't fit in middleware:
| request-scoped singletons, telemetry, feature-flag boot, etc.
|
| The autoload prefix→directory mapping moved to config/Autoload.php.
|
| Accessed via Env::get('providers').
*/

return [
    // App\Providers\TelemetryProvider::class,
];

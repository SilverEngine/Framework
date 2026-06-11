<?php

/*
|--------------------------------------------------------------------------
| Service Providers
|--------------------------------------------------------------------------
| Classes implementing Silver\Core\Bootstrap\ServiceProvider (or
| extending AbstractProvider) that the Kernel constructs once per
| request. Lifecycle in declaration order: register($container) for ALL
| providers first (bindings only), then before($req, $res) for all,
| then — after the response is flushed — after($req, $res).
|
| Use providers for kernel-level setup that doesn't fit in middleware:
| container bindings, request-scoped singletons, telemetry, feature-
| flag boot, etc.
|
| The autoload prefix→directory mapping moved to config/Autoload.php.
|
| Accessed via Env::get('providers').
*/

return [
    // App\Providers\TelemetryProvider::class,
];

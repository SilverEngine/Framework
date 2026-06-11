<?php
declare(strict_types=1);

namespace Silver\Core\Bootstrap;

use Silver\Core\Container;
use Silver\Http\Request;
use Silver\Http\Response;

/**
 * Contract for kernel service providers. Lifecycle per request, in
 * declaration order from config/Providers.php:
 *
 *  1. every provider is constructed (with the Kernel)
 *  2. `register($container)` runs for ALL providers — container
 *     bindings only; never resolve other services here, they may not
 *     be bound yet
 *  3. `before($req, $res)` runs for all providers — setup that needs
 *     the live request and may resolve anything registered in (2)
 *  4. the middleware pipeline + controller run, response is flushed
 *  5. `after($req, $res)` runs for all providers (telemetry, cleanup)
 *
 * Extend {@see AbstractProvider} to override only the phases you need.
 */
interface ServiceProvider
{
    public function register(Container $container): void;

    public function before(Request $request, Response $response): void;

    public function after(Request $request, Response $response): void;
}

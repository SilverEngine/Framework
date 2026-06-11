<?php
declare(strict_types=1);

namespace Silver\Core\Bootstrap;

use Silver\Core\Container;
use Silver\Http\Request;
use Silver\Http\Response;

/**
 * No-op base for {@see ServiceProvider} — extend and override only the
 * lifecycle phases your provider needs.
 */
abstract class AbstractProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
    }

    public function before(Request $request, Response $response): void
    {
    }

    public function after(Request $request, Response $response): void
    {
    }
}

<?php
declare(strict_types=1);

namespace Silver\Testing;

use Silver\Core\Kernel;
use Silver\Http\Request;
use Silver\Http\Response;

/**
 * Kernel variant for in-process test dispatch: runs the same provider
 * boot + middleware chain + route handling as {@see Kernel::run()},
 * but takes the middleware stack as an argument, returns the action's
 * result instead of printing, and never exits.
 */
final class TestKernel extends Kernel
{
    /** @param list<object> $middlewares pre-built global middleware instances */
    public function dispatch(array $middlewares, Request $request, Response $response): mixed
    {
        // Providers come from Env 'providers' (defaults to [] in tests);
        // calling this also primes the kernel's app reference.
        $this->loadProviders($request, $response);

        return $this->executeMiddlewares($middlewares, $request, $response);
    }
}

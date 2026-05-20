<?php
declare(strict_types=1);

namespace Silver\Core\Bootstrap;

use Silver\Http\Request;
use Silver\Http\Response;

/**
 * Contract for kernel service providers, aligned with how Kernel
 * actually invokes them: constructed with the Kernel, then
 * before()/after() receive the live Request/Response around the
 * middleware+controller run. (The old `register(mixed $app)` was never
 * called by Kernel and is removed; there are no implementors.)
 */
interface ServiceProvider
{
    public function before(Request $request, Response $response): void;
    public function after(Request $request, Response $response): void;
}

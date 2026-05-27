<?php
declare(strict_types=1);

namespace System\App\Controllers;

use Silver\Core\Controller;
use Silver\Core\Env;
use Silver\Core\Route;
use Silver\Engine\Ghost\WispResponse;
use Silver\Exception\NotFoundException;
use Silver\Support\Git;

/**
 * Dev-only scaffolder UI mounted at the route configured in
 * `config/Scaffolder.php` (default `/new`). Renders the `Scaffolder`
 * Wisp page. Disabled outside `APP_ENV=local` + `APP_DEBUG=true`, or
 * when the config flag is flipped off.
 */
final class NewController extends Controller
{
    public function __construct(private readonly Route $route) {}

    public function __invoke(): WispResponse
    {
        if (Env::name() !== 'local' || !Env::get('debug')) {
            throw new NotFoundException('Not found.');
        }

        return wisp('Scaffolder', [
            'phpVersion' => PHP_VERSION,
            'branch'     => Git::test() ?: 'detached',
            'routes'     => count($this->route->all()),
            'canScaffold' => true,
        ]);
    }
}

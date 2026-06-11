<?php
declare(strict_types=1);

namespace Silver\Console\Commands;

use Silver\Console\Contracts\Command;
use Silver\Console\Input;
use Silver\Console\Output;
use Silver\Core\Env;
use Silver\Core\Route;
use Silver\Core\RuntimeContext;

/**
 * `php silver route:list` — table of every registered route.
 */
final class RouteListCommand implements Command
{
    public function description(): string
    {
        return 'List all registered routes (method, path, action, name, middleware)';
    }

    public function handle(Input $input, Output $output): int
    {
        $root = RuntimeContext::current()->root;
        Env::construct($root);

        $router = app(Route::class);
        $router->reset();

        $route = $router; // route files reference $route
        foreach ((array) Env::get('routes', []) as $routeFile) {
            $path = $root . $routeFile . '.php';
            if (is_file($path)) {
                include $path;
            }
        }

        $routes = $router->all();
        if ($routes === []) {
            $output->info('No routes registered.');
            return 0;
        }

        $rows = [];
        foreach ($routes as $r) {
            $action = $r->action();
            $rows[] = [
                strtoupper($r->method()),
                $r->route(),
                $action instanceof \Closure ? 'Closure' : (is_string($action) ? $action : get_debug_type($action)),
                $r->name() ?? '',
                $r->middleware(),
            ];
        }

        $output->table(['METHOD', 'PATH', 'ACTION', 'NAME', 'MIDDLEWARE'], $rows);
        $output->line();
        $output->info(count($rows) . ' route(s)');

        return 0;
    }
}

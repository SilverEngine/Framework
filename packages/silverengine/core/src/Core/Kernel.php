<?php
declare(strict_types=1);

namespace Silver\Core;

use Silver\Http\Request;
use Silver\Http\Response;
use Silver\Exception\NotFoundException;

class Kernel
{
    private ?App $app = null;
    private array $providers = [];
    private array $middlewares = [];

    public function loadMiddlewares(): void
    {
        $container = App::instance()->instances();
        foreach (Env::get('middlewares', []) as $mw) {
            $this->middlewares[] = $container->make($mw);
        }
    }

    /**
     * Construct each Service Provider listed in config/Providers.php,
     * register it in the container, then fire `before()` in declaration
     * order. Matching `after()` calls run in {@see finalizeProviders()}
     * after the response is flushed.
     */
    public function loadProviders(Request $req, Response $res): void
    {
        foreach (Env::get('providers', []) as $providerClass) {
            $provider = new $providerClass($this);
            $this->providers[] = $provider;
            $this->app->register($provider);
        }

        foreach ($this->providers as $provider) {
            $provider->before($req, $res);
        }
    }

    public function finalizeProviders(Request $req, Response $res): void
    {
        foreach ($this->providers as $provider) {
            $provider->after($req, $res);
        }
    }

    public function loadRoutes(): void
    {
        $router = app(Route::class);

        // Reset so persistent processes (php -S, RoadRunner) pick up edits
        // to route files between requests rather than serving stale state.
        $router->reset();

        $cache = ROOT . 'storage/cache/routes.php';
        if (is_file($cache)) {
            $router->loadDefinitions(require $cache);
            return;
        }

        clearstatcache(true);
        foreach (Env::get('routes', []) as $routeFile) {
            $path = ROOT . $routeFile . '.php';
            // Even with opcache.enable_cli=0 the php -S dev server caches
            // parsed files between requests in some builds. Explicit
            // invalidate guarantees route files edited in-process (eg via
            // /__silver/scaffold) are picked up on the next request.
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($path, true);
            }
            // $route is available as the router singleton inside the
            // included file — `$route->get(...)`, `$route->group(...)`.
            (static function (string $path, Route $route): void {
                include $path;
            })($path, $router);
        }
    }

    public function handle(Request $request, Response $response): mixed
    {
        dt()->begin('route resolve', 'request');
        $route = $request->route()
            ?? throw new NotFoundException('Route for ' . $request->getUri() . ' not found.');
        dt()->end('route resolve', 'request');

        dt()->begin('controller resolve', 'controller');
        $callable = $this->findCallable($route);
        dt()->end('controller resolve', 'controller');

        dt()->begin('controller action', 'controller');
        try {
            return app(DI::class)->call(
                $callable,
                array_merge(
                    $this->app->instances()->getAll(),
                    $route->variables(),
                ),
            );
        } finally {
            dt()->end('controller action', 'controller');
        }
    }

    protected function executeMiddlewares(array $mws, Request $req, Response $res): mixed
    {
        if (count($mws) > 0) {
            $mw = array_shift($mws);
            $cls = $mw::class;
            $label = substr($cls, strrpos($cls, '\\') + 1);

            dt()->begin($label, 'middleware');
            try {
                return $mw->execute(
                    $req,
                    $res,
                    fn () => $this->executeMiddlewares($mws, $req, $res),
                );
            } finally {
                dt()->end($label, 'middleware');
            }
        }

        return $this->handle($req, $res);
    }

    private function findCallable(Route $route): callable|array
    {
        $action = $route->action();

        // Closures.
        if ($action instanceof \Closure) {
            return $action;
        }

        // Array form: [FQCN::class, 'method'] — explicit method on a class.
        if (is_array($action)) {
            $fqcn   = $action[0] ?? null;
            $method = $action[1] ?? '__invoke';

            if (!is_string($fqcn) || !class_exists($fqcn)) {
                throw new NotFoundException("Controller class not found: " . (string) $fqcn);
            }

            $controller = $this->app->instances()->make($fqcn);

            if (!method_exists($controller, $method)) {
                throw new NotFoundException("Action not found {$method} on {$fqcn}");
            }

            return [$controller, $method];
        }

        if (str_contains($action, '\\')) {
            clearstatcache(true);
        }
        if (class_exists($action)) {
            $controller = $this->app->instances()->make($action);
            if (!method_exists($controller, '__invoke')) {
                throw new NotFoundException("Invokable controller missing __invoke: {$action}");
            }
            return [$controller, '__invoke'];
        }

        // Short-name string form: 'Foo@bar' or 'Foo' (conventional location lookup).
        if (str_contains($action, '@')) {
            [$class, $method] = explode('@', $action);
        } else {
            [$class, $method] = [$action, '__invoke'];
        }

        $locations = [
            [$this->app->path(), 'App\\'],
            [$this->app->systemPath(), 'System\\App\\'],
        ];

        foreach ($locations as [$folder, $nsPrefix]) {
            $fullPath = $folder . 'Controllers/' . $class . 'Controller.php';

            if (file_exists($fullPath)) {
                include_once $fullPath;
                $fullClass = $nsPrefix . 'Controllers\\' . $class . 'Controller';
                $controller = $this->app->instances()->make($fullClass);

                if (method_exists($controller, $method)) {
                    return [$controller, $method];
                }

                throw new NotFoundException("Action not found {$method}@{$class}");
            }
        }

        throw new NotFoundException("Controller not found: {$method}@{$class}");
    }

    public function run(): never
    {
        $this->app = $app = App::instance();

        dt()->begin('request init', 'request');
        $app->register(
            $req = new Request(),
            $res = new Response(),
        );
        dt()->end('request init', 'request');
        dt()->mark('request received', 'request');

        dt()->begin('providers', 'kernel');
        $this->loadProviders($req, $res);
        dt()->end('providers', 'kernel');

        dt()->begin('middlewares + controller', 'kernel');
        $ret = $this->executeMiddlewares($this->middlewares, $req, $res);
        dt()->end('middlewares + controller', 'kernel');

        if ($ret !== null) {
            $res->setBody($ret);
        }

        dt()->begin('view render', 'view');
        $res->send();
        dt()->end('view render', 'view');
        dt()->mark('response sent', 'view');

        // Flush PHP output so deferred work doesn't block the client.
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }

        // Hooks registered via $c->afterDeferred(...) run here — after the
        // response is on the wire — so logging / metrics / audit writes
        // never add to perceived latency.
        dt()->begin('deferred hooks', 'kernel');
        app(Hook::class)->runDeferred();
        dt()->end('deferred hooks', 'kernel');

        dt()->begin('finalize providers', 'kernel');
        $this->finalizeProviders($req, $res);
        dt()->end('finalize providers', 'kernel');

        recorder()->record(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
            http_response_code() ?: 200,
        );

        exit;
    }
}

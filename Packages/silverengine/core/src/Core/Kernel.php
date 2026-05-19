<?php
declare(strict_types=1);

namespace Silver\Core;

use Silver\Http\Request;
use Silver\Http\Response;
use Silver\Exception\NotFoundException;
use Silver\Support\DebugTimer;
use Silver\Support\RequestRecorder;

class Kernel
{
    private ?App $app = null;
    private array $services = [];
    private array $middlewares = [];

    public function loadMiddlewares(): void
    {
        $container = App::instance()->instances();
        foreach (Env::get('middlewares', []) as $mw) {
            $this->middlewares[] = $container->make($mw);
        }
    }

    public function loadServices(Request $req, Response $res): void
    {
        $services = Env::get('services', []);
        foreach ($services as $serviceClass) {
            $service = new $serviceClass($this);
            $this->services[] = $service;
            $this->app->register($service);
        }

        foreach ($this->services as $service) {
            $service->before($req, $res);
        }
    }

    public function finalizeServices(Request $req, Response $res): void
    {
        foreach ($this->services as $service) {
            $service->after($req, $res);
        }
    }

    public function loadRoutes(): void
    {
        $cache = ROOT . 'Storage/cache/routes.php';
        if (is_file($cache)) {
            Route::loadDefinitions(require $cache);
            return;
        }

        foreach (Env::get('routes', []) as $route) {
            include_once ROOT . $route . '.php';
        }
    }

    public function handle(Request $request, Response $response): mixed
    {
        DebugTimer::begin('route resolve', 'request');
        $route = $request->route()
            ?? throw new NotFoundException('Route for ' . $request->getUri() . ' not found.');
        DebugTimer::end('route resolve', 'request');

        DebugTimer::begin('controller resolve', 'controller');
        $callable = $this->findCallable($route);
        DebugTimer::end('controller resolve', 'controller');

        DebugTimer::begin('controller action', 'controller');
        try {
            return DI::call(
                $callable,
                array_merge(
                    $this->app->instances()->getAll(),
                    $route->variables(),
                ),
            );
        } finally {
            DebugTimer::end('controller action', 'controller');
        }
    }

    protected function executeMiddlewares(array $mws, Request $req, Response $res): mixed
    {
        if (count($mws) > 0) {
            $mw = array_shift($mws);
            $cls = $mw::class;
            $label = substr($cls, strrpos($cls, '\\') + 1);

            DebugTimer::begin($label, 'middleware');
            try {
                return $mw->execute(
                    $req,
                    $res,
                    fn () => $this->executeMiddlewares($mws, $req, $res),
                );
            } finally {
                DebugTimer::end($label, 'middleware');
            }
        }

        return $this->handle($req, $res);
    }

    private function findCallable(Route $route): callable|array
    {
        $action = $route->action();

        if (is_callable($action)) {
            return $action;
        }

        [$class, $method] = explode('@', $action);

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

        $app->register(
            $req = new Request(),
            $res = new Response(),
        );
        DebugTimer::mark('request received', 'request');

        DebugTimer::begin('services', 'kernel');
        $this->loadServices($req, $res);
        DebugTimer::end('services', 'kernel');

        DebugTimer::begin('middlewares + controller', 'kernel');
        $ret = $this->executeMiddlewares($this->middlewares, $req, $res);
        DebugTimer::end('middlewares + controller', 'kernel');

        if ($ret !== null) {
            $res->setBody($ret);
        }

        DebugTimer::begin('view render', 'view');
        $res->send();
        DebugTimer::end('view render', 'view');
        DebugTimer::mark('response sent', 'view');

        $this->finalizeServices($req, $res);

        RequestRecorder::record(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
            http_response_code() ?: 200,
        );

        exit;
    }
}

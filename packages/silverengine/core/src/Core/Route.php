<?php
declare(strict_types=1);

namespace Silver\Core;

/**
 * Route serves two roles on the same class:
 *
 *  - As the singleton resolved through the container (`App::instance()
 *    ->instances()->make(Route::class)`), it is the route registry —
 *    `get()` / `post()` / `group()` / `find()` / `getRoute()` etc.
 *  - As a value object (constructed internally by `register()`), it
 *    holds one route's method/path/action plus matched variables.
 *
 * Registry state (routes, routeIndex, prefix, jailStack, types) is only
 * populated on the singleton; value-object instances created by
 * `register()` ignore those fields.
 */
final class Route
{
    // ---- Per-route value-object state -------------------------------
    private string $method = '';
    private string $route = '';
    private mixed $action = null;
    /** @var list<string> */
    private array $jails = [];
    private ?string $name = null;
    private string $middleware = 'public';
    private ?string $type = null;
    /** @var array<string,mixed> */
    private array $variables = [];

    // ---- Registry state (only used by the singleton) ----------------
    /** @var list<self> */
    private array $routes = [];
    /** @var array<string,self> */
    private array $routeIndex = [];
    private string $prefix = '';
    /** @var list<string> */
    private array $jailStack = [];

    /** @var array<string,string> */
    private array $types = [
        'int'    => '/^[0-9]+$/',
        'string' => '/^[a-zA-Z]+/$',
        'hash'   => 'md5',
    ];

    // ---- Value-object accessors -------------------------------------

    public function action(): mixed
    {
        return $this->action;
    }

    public function type(): ?string
    {
        return $this->type;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function middleware(): string
    {
        return $this->middleware;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function route(): string
    {
        return $this->route;
    }

    /** @return array<string,mixed> */
    public function variables(): array
    {
        return $this->variables;
    }

    public function url(array $vars = []): string
    {
        $parts = explode('/', $this->route);
        $url = [];
        $i_variable = 0;

        foreach ($parts as $part) {
            if (empty($part)) {
                continue;
            }

            if (str_starts_with($part, '{')) {
                $key = trim($part, '{}');
                $value = isset($vars[0])
                    ? ($vars[$i_variable++] ?? throw new \Exception("Route {$this->route} has no variable $key."))
                    : ($vars[$key] ?? throw new \Exception("Route {$this->route} has no variable $key."));
                $url[] = $value;
            } else {
                $url[] = $part;
            }
        }

        return BASEPATH . '/' . implode('/', $url);
    }

    public function segment(int $index): mixed
    {
        $segments = explode('/', $this->route);
        $seg = $segments[$index];

        if (str_starts_with($seg, '{')) {
            $seg = substr($seg, 1, -1);
            if (str_ends_with($seg, '?')) {
                $seg = substr($seg, 0, -1);
            }
            return $this->variables[$seg];
        }

        return $seg;
    }

    public function check(string $method, string $url, array $types = []): bool
    {
        if ($this->method !== 'any' && $method !== $this->method) {
            return false;
        }

        // Singleton owns the type rules; value objects receive them via check().
        $typeRules = $types ?: $this->types;

        $route = explode('/', rtrim($this->route, '/'));
        $urlParts = explode('/', rtrim($url, '/'));

        while ($route) {
            if (strlen($route[0]) && str_starts_with($route[0], '{')) {
                $required = true;
                $varname = substr($route[0], 1, -1);

                if (str_ends_with($varname, '?')) {
                    $required = false;
                    $varname = substr($varname, 0, -1);
                }

                if (str_contains($varname, ':')) {
                    [$varname, $typeName] = explode(':', $varname);
                    $rule = $typeRules[$typeName] ?? throw new \Exception("Invalid route variable type $typeName.");

                    if (str_starts_with($rule, '/')) {
                        if (!preg_match($rule, $urlParts[0] ?? '')) {
                            throw new \Exception("Invalid variable type.");
                        }
                    } elseif (function_exists($rule)) {
                        $urlParts[0] = $rule($urlParts[0]);
                    } else {
                        throw new \Exception("Invalid rule for type $typeName.");
                    }
                }

                if ($urlParts) {
                    $this->variables[$varname] = $urlParts[0];
                } elseif ($required) {
                    return false;
                } else {
                    $this->variables[$varname] = null;
                }
            } else {
                if (!$urlParts || $route[0] !== $urlParts[0]) {
                    return false;
                }
            }

            array_shift($route);
            array_shift($urlParts);
        }

        if ($urlParts) {
            return false;
        }

        // Check jails (deprecated)
        foreach ($this->jails as $jail) {
            $jailParts = explode('@', $jail);
            $class = $jailParts[0];
            $jailMethod = $jailParts[1] ?? 'protect';
            if (!str_starts_with($class, '\\')) {
                $class = '\\App\\Jail\\' . $class;
            }
            if (!$class::$jailMethod()) {
                return false;
            }
        }

        return true;
    }

    // ---- Registry surface (call on the singleton) -------------------

    /** @return list<self> */
    public function all(): array
    {
        return $this->routes;
    }

    public function group(array $args, callable $fn): void
    {
        if (isset($args['jail'])) {
            $this->jailStack[] = $args['jail'];
        }

        $oldPrefix = $this->prefix;
        $this->prefix .= '/' . ($args['prefix'] ?? '');

        $fn();

        $this->prefix = $oldPrefix;

        if (isset($args['jail'])) {
            array_pop($this->jailStack);
        }
    }

    public function find(string $requestUrl, string $requestMethod): ?self
    {
        foreach ($this->routes as $route) {
            if ($route->check($requestMethod, $requestUrl, $this->types)) {
                return $route;
            }
        }
        return null;
    }

    public function register(
        string $method,
        string $route,
        mixed $action,
        ?string $name = null,
        string $middleware = 'public',
        string $type = '',
    ): void {
        $route = $this->prefix . $route;
        foreach (explode('|', $method) as $m) {
            $r = new self();
            $r->method = strtolower($m);
            $r->route = $route;
            $r->action = $action;
            $r->jails = $this->jailStack;
            $r->name = $name;
            $r->middleware = $middleware;
            $r->type = $type;
            $this->routes[] = $r;
            if ($name !== null) {
                $this->routeIndex[$name] = $r;
            }
        }
    }

    public function get(string $route, mixed $action, ?string $name = null, string $middleware = 'public'): void
    {
        $this->register('get', $route, $action, $name, $middleware, 'get');
    }

    public function post(string $route, mixed $action, ?string $name = null, string $middleware = 'public'): void
    {
        $this->register('post', $route, $action, $name, $middleware, 'post');
    }

    public function put(string $route, mixed $action, ?string $name = null, string $middleware = 'public'): void
    {
        $this->register('put', $route, $action, $name, $middleware, 'put');
    }

    public function delete(string $route, mixed $action, ?string $name = null, string $middleware = 'public'): void
    {
        $this->register('delete', $route, $action, $name, $middleware, 'delete');
    }

    public function resource(string $route, string $action, ?string $name = null, string $middleware = 'public'): void
    {
        if (($pos = strpos($action, '@')) !== false) {
            $action = substr($action, 0, $pos);
        }

        $route = rtrim($route, '/');

        $this->register('get', $route, $action . '@get', $name, $middleware, 'resources');
        $this->register('post', $route, $action . '@post', $name, $middleware, 'resources');

        foreach (['get', 'put', 'patch', 'delete'] as $method) {
            $this->register($method, $route . '/{id}', $action . '@' . $method, $name, $middleware, 'resources');
        }
    }

    public function any(string $route, mixed $action, ?string $name = null, string $middleware = 'public'): void
    {
        $this->register('any', $route, $action, $name, $middleware, 'any');
    }

    public function getRoute(string $name): self
    {
        return $this->routeIndex[$name] ?? throw new \Exception("Route $name not found.");
    }

    /**
     * Wipe the registered route table. Used by {@see Kernel::loadRoutes()}
     * at the start of each request so persistent state in long-lived
     * PHP processes doesn't accumulate across requests.
     */
    public function reset(): void
    {
        $this->routes = [];
        $this->routeIndex = [];
        $this->prefix = '';
        $this->jailStack = [];
    }

    /**
     * Flat, serialisable definitions of every registered route for the
     * route cache. Returns null if any route uses a Closure action
     * (closures can't be cached) so the caller falls back to including
     * the route files — same policy as Laravel's route:cache.
     *
     * @return list<array{0:string,1:string,2:string|array,3:?string,4:string,5:string}>|null
     */
    public function definitions(): ?array
    {
        $defs = [];
        foreach ($this->routes as $r) {
            $action = $r->action();
            if ($action instanceof \Closure) {
                return null;
            }
            $defs[] = [
                $r->method(),
                $r->route(),
                $action,
                $r->name(),
                $r->middleware(),
                $r->type() ?? '',
            ];
        }
        return $defs;
    }

    /**
     * Rebuild the route table from cached definitions.
     *
     * @param list<array> $defs
     */
    public function loadDefinitions(array $defs): void
    {
        foreach ($defs as $d) {
            $this->register($d[0], $d[1], $d[2], $d[3], $d[4], $d[5]);
        }
    }
}

<?php
declare(strict_types=1);

namespace Silver\Core;

class Route
{
    private string $method;
    private string $route;
    private mixed $action;
    private array $jails;
    private ?string $name;
    private string $middleware;
    private ?string $type;
    private array $variables = [];

    private static array $jailStack = [];
    private static string $prefix = '';
    private static array $routes = [];
    private static array $routeIndex = [];

    private static array $types = [
        'int'    => '/^[0-9]+$/',
        'string' => '/^[a-zA-Z]+/$',
        'hash'   => 'md5',
    ];

    public function __construct(
        string $method,
        string $route,
        mixed $action,
        ?string $name = null,
        string $middleware = 'public',
        ?string $type = null,
    ) {
        $this->method = strtolower($method);
        $this->route = $route;
        $this->action = $action;
        $this->jails = self::$jailStack;
        $this->name = $name;
        $this->middleware = $middleware;
        $this->type = $type;
    }

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

    public function variables(): array
    {
        return $this->variables;
    }

    public function route(): string
    {
        return $this->route;
    }

    public static function all(): array
    {
        return self::$routes;
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

    public function check(string $method, string $url): bool
    {
        if ($this->method !== 'any' && $method !== $this->method) {
            return false;
        }

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
                    $rule = self::$types[$typeName] ?? throw new \Exception("Invalid route variable type $typeName.");

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

    public static function group(array $args, callable $fn): void
    {
        if (isset($args['jail'])) {
            self::$jailStack[] = $args['jail'];
        }

        $old_prefix = self::$prefix;
        self::$prefix .= '/' . ($args['prefix'] ?? '');

        $fn();

        self::$prefix = $old_prefix;

        if (isset($args['jail'])) {
            array_pop(self::$jailStack);
        }
    }

    public static function find(string $requestUrl, string $requestMethod): ?self
    {
        foreach (self::$routes as $route) {
            if ($route->check($requestMethod, $requestUrl)) {
                return $route;
            }
        }
        return null;
    }

    public static function register(
        string $method,
        string $route,
        mixed $action,
        ?string $name = null,
        string $middleware = 'public',
        string $type = '',
    ): void {
        $route = self::$prefix . $route;
        foreach (explode('|', $method) as $m) {
            $r = new self($m, $route, $action, $name, $middleware, $type);
            self::$routes[] = $r;
            if ($name !== null) {
                self::$routeIndex[$name] = $r;
            }
        }
    }

    public static function get(string $route, mixed $action, ?string $name = null, string $middleware = 'public'): void
    {
        self::register('get', $route, $action, $name, $middleware, 'get');
    }

    public static function post(string $route, mixed $action, ?string $name = null, string $middleware = 'public'): void
    {
        self::register('post', $route, $action, $name, $middleware, 'post');
    }

    public static function put(string $route, mixed $action, ?string $name = null, string $middleware = 'public'): void
    {
        self::register('put', $route, $action, $name, $middleware, 'put');
    }

    public static function delete(string $route, mixed $action, ?string $name = null, string $middleware = 'public'): void
    {
        self::register('delete', $route, $action, $name, $middleware, 'delete');
    }

    public static function resource(string $route, string $action, ?string $name = null, string $middleware = 'public'): void
    {
        if (($pos = strpos($action, '@')) !== false) {
            $action = substr($action, 0, $pos);
        }

        $route = rtrim($route, '/');

        self::register('get', $route, $action . '@get', $name, $middleware, 'resources');
        self::register('post', $route, $action . '@post', $name, $middleware, 'resources');

        foreach (['get', 'put', 'patch', 'delete'] as $method) {
            self::register($method, $route . '/{id}', $action . '@' . $method, $name, $middleware, 'resources');
        }
    }

    public static function any(string $route, mixed $action, ?string $name = null, string $middleware = 'public'): void
    {
        self::register('any', $route, $action, $name, $middleware, 'any');
    }

    public static function getRoute(string $name): self
    {
        return self::$routeIndex[$name] ?? throw new \Exception("Route $name not found.");
    }
}

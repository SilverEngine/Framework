<?php
declare(strict_types=1);

namespace Silver\Core;

class Route
{
    private string $_method;
    private string $_route;
    private mixed $_action;
    private array $_jails;
    private ?string $_name;
    private string $_middleware;
    private ?string $_type;
    private array $_variables = [];

    private static array $jails = [];
    private static string $_prefix = '';
    private static array $_routes = [];
    private static array $_route_index = [];

    private static array $_types = [
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
        $this->_method = strtolower($method);
        $this->_route = $route;
        $this->_action = $action;
        $this->_jails = self::$jails;
        $this->_name = $name;
        $this->_middleware = $middleware;
        $this->_type = $type;
    }

    public function action(): mixed
    {
        return $this->_action;
    }

    public function type(): ?string
    {
        return $this->_type;
    }

    public function name(): ?string
    {
        return $this->_name;
    }

    public function middleware(): string
    {
        return $this->_middleware;
    }

    public function method(): string
    {
        return $this->_method;
    }

    public function url(array $vars = []): string
    {
        $parts = explode('/', $this->_route);
        $url = [];
        $i_variable = 0;

        foreach ($parts as $part) {
            if (empty($part)) {
                continue;
            }

            if (str_starts_with($part, '{')) {
                $key = trim($part, '{}');
                $value = isset($vars[0])
                    ? ($vars[$i_variable++] ?? throw new \Exception("Route {$this->_route} has no variable $key."))
                    : ($vars[$key] ?? throw new \Exception("Route {$this->_route} has no variable $key."));
                $url[] = $value;
            } else {
                $url[] = $part;
            }
        }

        return BASEPATH . '/' . implode('/', $url);
    }

    public function variables(): array
    {
        return $this->_variables;
    }

    public function route(): string
    {
        return $this->_route;
    }

    public static function all(): array
    {
        return self::$_routes;
    }

    public function segment(int $index): mixed
    {
        $segments = explode('/', $this->_route);
        $seg = $segments[$index];

        if (str_starts_with($seg, '{')) {
            $seg = substr($seg, 1, -1);
            if (str_ends_with($seg, '?')) {
                $seg = substr($seg, 0, -1);
            }
            return $this->_variables[$seg];
        }

        return $seg;
    }

    public function check(string $method, string $url): bool
    {
        if ($this->_method !== 'any' && $method !== $this->_method) {
            return false;
        }

        $route = explode('/', rtrim($this->_route, '/'));
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
                    $rule = self::$_types[$typeName] ?? throw new \Exception("Invalid route variable type $typeName.");

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
                    $this->_variables[$varname] = $urlParts[0];
                } elseif ($required) {
                    return false;
                } else {
                    $this->_variables[$varname] = null;
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
        foreach ($this->_jails as $jail) {
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
            self::$jails[] = $args['jail'];
        }

        $old_prefix = self::$_prefix;
        self::$_prefix .= '/' . ($args['prefix'] ?? '');

        $fn();

        self::$_prefix = $old_prefix;

        if (isset($args['jail'])) {
            array_pop(self::$jails);
        }
    }

    public static function find(string $requestUrl, string $requestMethod): ?self
    {
        foreach (self::$_routes as $route) {
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
        $route = self::$_prefix . $route;
        foreach (explode('|', $method) as $m) {
            $r = new self($m, $route, $action, $name, $middleware, $type);
            self::$_routes[] = $r;
            if ($name !== null) {
                self::$_route_index[$name] = $r;
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
        return self::$_route_index[$name] ?? throw new \Exception("Route $name not found.");
    }
}

<?php
declare(strict_types=1);

use Silver\Helpers\URL;
use Silver\Helpers\HTMLElement as El;
use Silver\Core\Route;
use Silver\Http\View;

if (!function_exists('dd')) {
    function dd(mixed $data, bool $dump = false): never
    {
        if ($dump) {
            var_dump('<pre>', $data, '<pre>');
        } else {
            var_dump($data);
        }
        exit();
    }
}

if (!function_exists('ndd')) {
    function ndd(mixed $data, bool $dump = false): void
    {
        if ($dump) {
            var_dump('<pre>', $data, '<pre>');
        } else {
            var_dump($data);
        }
    }
}

if (!function_exists('debug')) {
    function debug(mixed $data, bool $dump = false): void
    {
        if ($dump) {
            var_dump($data);
        } else {
            var_dump('<pre>', $data, '<pre>');
        }
    }
}

if (!function_exists('view')) {
    function view(string $name, array $data = []): mixed
    {
        return View::make($name, $data);
    }
}

if (!function_exists('url')) {
    function url(string $path = '/', array $query = []): URL
    {
        if (str_starts_with($path, '/')) {
            $url = URL::make($path, $query);
            $url->merge(URL . $url->part('absPath'));
            return $url;
        }

        $url = URL::make(CURRENT_URL, $query);
        $url->merge($path);
        return $url;
    }
}

if (!function_exists('asset')) {
    function asset(string $url, array $query = []): URL
    {
        $u = URL::make($url, $query);
        $u->merge(URL . '/public' . $u->part('absPath', ''));
        return $u;
    }
}

if (!function_exists('alink')) {
    function alink(string $text, string $url, array $attrs = []): El
    {
        $el = new El('a', array_merge(['href' => $url], $attrs));
        return $el->appendChild($text ?: $url);
    }
}

if (!function_exists('css')) {
    function css(string $url, array $attrs = []): El
    {
        return new El('link', array_merge(['type' => 'text/css', 'href' => $url], $attrs));
    }
}

if (!function_exists('js')) {
    function js(string $url, array $attrs = []): El
    {
        return new El('script', array_merge(['type' => 'text/javascript', 'src' => (string) $url], $attrs));
    }
}

if (!function_exists('route')) {
    function route(string $name, array $args = []): string
    {
        return Route::getRoute($name)->url($args);
    }
}

if (!function_exists('mapArrayKeys')) {
    function mapArrayKeys(array $array, callable $callback): array
    {
        $arr = [];
        foreach ($array as $key => $value) {
            $arr[$callback($key)] = $value;
        }
        return $arr;
    }
}

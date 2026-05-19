<?php
declare(strict_types=1);

namespace Silver\Helpers;

use Silver\Exception\Exception;

class URL
{
    private array $parts = [];

    public function __construct(string $url)
    {
        $this->merge($url);
    }

    public static function make(string $url, array $query = []): static
    {
        $instance = new static($url);
        if ($query) {
            $instance->setPart('query', $query);
        }
        return $instance;
    }

    public function setPart(string $key, mixed $value, bool $override = true): static
    {
        if ($key === 'path') {
            $key = (isset($value[0]) && $value[0] === '/') ? 'absPath' : 'relPath';
        }

        if (!isset($this->parts[$key]) || $override) {
            $this->parts[$key] = $value;
        }

        return $this;
    }

    public function getPath(): string
    {
        $abs = $this->part('absPath', '');
        $rel = $this->part('relPath', '');

        if ($abs !== '' && $rel !== '' && !str_ends_with($abs, '/')) {
            $abs .= '/';
        }

        return $abs . $rel;
    }

    public function part(string $key, mixed $default = null): mixed
    {
        if ($key === 'path') {
            return $this->getPath();
        }

        return $this->parts[$key] ?? $default;
    }

    public function merge(string $url, bool $override = true): static
    {
        foreach (parse_url($url) as $key => $value) {
            $this->setPart($key, $value, $override);
        }

        return $this;
    }

    public function __call(string $method, array $args): mixed
    {
        if (str_starts_with($method, 'set')) {
            $key = lcfirst(substr($method, 3));
            $this->setPart($key, $args[0]);
            return $this;
        }

        if (str_starts_with($method, 'get')) {
            $key = lcfirst(substr($method, 3));
            return $this->part($key);
        }

        throw new Exception("Call undefined method " . static::class . "::" . $method . '()');
    }

    public function __toString(): string
    {
        $url = '';

        if ($scheme = $this->part('scheme')) {
            $url .= $scheme . '://';
        }

        if ($host = $this->part('host')) {
            if ($user = $this->part('user')) {
                $url .= urlencode($user);
                if ($pass = $this->part('pass')) {
                    $url .= ':' . urlencode($pass);
                }
                $url .= '@';
            }
            $url .= urlencode($host);
        }

        if ($path = $this->part('path')) {
            $url .= implode('/', array_map('urlencode', explode('/', $path)));
        }

        if (($query = $this->part('query')) && is_array($query)) {
            $url .= '?' . http_build_query($query);
        }

        if ($fragment = $this->part('fragment')) {
            $url .= '#' . $fragment;
        }

        return $url;
    }
}

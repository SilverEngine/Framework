<?php
declare(strict_types=1);

use Silver\Helpers\URL;
use Silver\Helpers\HTMLElement as El;
use Silver\Core\App;
use Silver\Core\Container;
use Silver\Core\Route;
use Silver\Http\View;

if (!function_exists('dt')) {
    /** Shorthand for the {@see \Silver\Support\DebugTimer} singleton. */
    function dt(): \Silver\Support\DebugTimer
    {
        return App::instance()->instances()->make(\Silver\Support\DebugTimer::class);
    }
}

if (!function_exists('recorder')) {
    /** Shorthand for the {@see \Silver\Support\RequestRecorder} singleton. */
    function recorder(): \Silver\Support\RequestRecorder
    {
        return App::instance()->instances()->make(\Silver\Support\RequestRecorder::class);
    }
}

if (!function_exists('app')) {
    /**
     * Shorthand for `App::instance()->instances()->make($abstract)`.
     * With no argument, returns the container itself. With a class
     * string, resolves and returns the singleton.
     *
     * @template T of object
     * @param class-string<T>|null $abstract
     * @return ($abstract is null ? Container : T)
     */
    function app(?string $abstract = null): mixed
    {
        $container = App::instance()->instances();
        return $abstract === null ? $container : $container->make($abstract);
    }
}

if (!function_exists('mailer')) {
    /** Shorthand for the {@see \Silver\Mail\Mailer} singleton. */
    function mailer(): \Silver\Mail\Mailer
    {
        return App::instance()->instances()->make(\Silver\Mail\Mailer::class);
    }
}

if (!function_exists('trans')) {
    /**
     * Translate "file.dot.path" with :placeholder interpolation.
     * Missing keys come back verbatim (greppable).
     *
     * @param array<string, mixed> $replace
     */
    function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        return App::instance()->instances()
            ->make(\Silver\Support\Translator::class)
            ->get($key, $replace, $locale);
    }
}

if (!function_exists('trans_choice')) {
    /**
     * Pluralized translation — `'one apple|:count apples'` picked by
     * $count (`{n}` / `[a,*]` conditions supported), :count injected.
     *
     * @param array<string, mixed> $replace
     */
    function trans_choice(string $key, int|float $count, array $replace = [], ?string $locale = null): string
    {
        return App::instance()->instances()
            ->make(\Silver\Support\Translator::class)
            ->choice($key, $count, $replace, $locale);
    }
}

if (!function_exists('collect')) {
    /**
     * Wrap an iterable in a {@see \Silver\Support\Collection}.
     *
     * @template T
     * @param iterable<int|string, T> $items
     * @return \Silver\Support\Collection<T>
     */
    function collect(iterable $items = []): \Silver\Support\Collection
    {
        return \Silver\Support\Collection::make($items);
    }
}

if (!function_exists('cache')) {
    /** Shorthand for the {@see \Silver\Support\Cache} singleton. */
    function cache(): \Silver\Support\Cache
    {
        return App::instance()->instances()->make(\Silver\Support\Cache::class);
    }
}

if (!function_exists('gate')) {
    /** Shorthand for the {@see \Silver\Auth\Access\Gate} singleton. */
    function gate(): \Silver\Auth\Access\Gate
    {
        return App::instance()->instances()->make(\Silver\Auth\Access\Gate::class);
    }
}

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
        $context = Silver\Core\RuntimeContext::current();

        if (str_starts_with($path, '/')) {
            $url = URL::make($path, $query);
            $url->merge($context->url . $url->part('absPath'));
            return $url;
        }

        $url = URL::make($context->currentUrl, $query);
        $url->merge($path);
        return $url;
    }
}

if (!function_exists('asset')) {
    function asset(string $url, array $query = []): URL
    {
        $u = URL::make($url, $query);
        $u->merge(Silver\Core\RuntimeContext::current()->url . '/public' . $u->part('absPath', ''));
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

if (!function_exists('wisp')) {
    function wisp(string $component, array $props = []): \Silver\Engine\Ghost\WispResponse
    {
        return \Silver\Engine\Ghost\Wisp::render($component, $props);
    }
}

if (!function_exists('auth')) {
    /**
     * The resolved auth guard — `auth()->user()`, `auth()->check()`,
     * or a named one via `auth('api')`. (The AuthManager itself is
     * available as app(AuthManager::class) for advanced wiring.)
     */
    function auth(?string $guard = null): \Silver\Auth\Contracts\Guard
    {
        return app(\Silver\Auth\AuthManager::class)->guard($guard);
    }
}

if (!function_exists('errors')) {
    /**
     * Validation errors flashed by the last request. With no argument
     * returns the full field → list-of-messages map; with a field name
     * returns that field's first message or null. Pairs with the
     * always-present `errors` prop on Wisp pages.
     */
    function errors(?string $field = null): array|string|null
    {
        $all = \Silver\Http\Session::get('_errors');
        $all = is_array($all) ? $all : [];
        if ($field === null) {
            return $all;
        }
        $messages = $all[$field] ?? [];
        return is_array($messages) ? ($messages[0] ?? null) : (string) $messages;
    }
}

if (!function_exists('old')) {
    /** Input flashed by the last failed validation: `old('email')`. */
    function old(string $field, mixed $default = null): mixed
    {
        $old = \Silver\Http\Session::get('_old');
        return is_array($old) ? ($old[$field] ?? $default) : $default;
    }
}

if (!function_exists('csrf_token')) {
    /** Current per-session CSRF token. Stable across requests until rotate(). */
    function csrf_token(): string
    {
        return app(\Silver\Http\Csrf\TokenStore::class)->current();
    }
}

if (!function_exists('csrf_field')) {
    /** Hidden input with the current CSRF token — drop into any classic <form>. */
    function csrf_field(): string
    {
        $token = htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<input type="hidden" name="_token" value="' . $token . '">';
    }
}

if (!function_exists('route')) {
    function route(string $name, array $args = []): string
    {
        return app(Route::class)->getRoute($name)->url($args);
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

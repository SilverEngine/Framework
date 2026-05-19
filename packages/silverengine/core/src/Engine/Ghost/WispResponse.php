<?php
declare(strict_types=1);

namespace Silver\Engine\Ghost;

use Closure;
use Silver\Core\Contracts\RenderInterface;
use Silver\Http\Request;
use Silver\Http\View;

/**
 * The value a controller returns from `wisp('Page', $props)`.
 *
 * Implements RenderInterface so the existing Response pipeline can drive it:
 *   - render() -> full HTML shell with the page object baked into #app
 *   - data()   -> the page object array (used for the X-Inertia JSON path)
 *
 * Speaks the Inertia wire protocol (X-Inertia-* headers) so the official
 * @inertiajs/vue3 client works unchanged.
 */
final class WispResponse implements RenderInterface
{
    public function __construct(
        private readonly string $component,
        private array $props = [],
    ) {
    }

    private function request(): Request
    {
        try {
            return Request::instance() ?? new Request();
        } catch (\Throwable) {
            // Not registered (CLI / tests) — build a fresh one.
            return new Request();
        }
    }

    /** The Inertia page object: component, props, url, version[, deferredProps]. */
    public function data(): array
    {
        $req = $this->request();

        // Unified shared data + composers for this component, then props win.
        $merged = array_merge(View::sharedFor($this->component), $this->props);

        $partialComponent = $req->headerValue('X-Inertia-Partial-Component');
        $isPartial = $partialComponent !== null && $partialComponent === $this->component;

        $only = null;
        if ($isPartial && $req->hasHeader('X-Inertia-Partial-Data')) {
            $only = array_filter(array_map(
                'trim',
                explode(',', (string) $req->headerValue('X-Inertia-Partial-Data')),
            ));
        }

        $props = [];
        $deferred = [];

        foreach ($merged as $key => $value) {
            if ($value instanceof DeferProp) {
                if ($isPartial && $this->requested($key, $only)) {
                    $props[$key] = $value();
                } elseif (!$isPartial) {
                    $deferred[$value->group][] = $key;
                }
                continue;
            }

            if ($value instanceof LazyProp) {
                if ($isPartial && $this->requested($key, $only)) {
                    $props[$key] = $value();
                }
                continue;
            }

            if ($only !== null && !in_array($key, $only, true)) {
                continue;
            }

            $props[$key] = ($value instanceof Closure) ? $value() : $value;
        }

        $page = [
            'component' => $this->component,
            'props'     => (object) $props,
            'url'       => $this->url(),
            'version'   => Vite::version(),
        ];

        if (!$isPartial && $deferred !== []) {
            $page['deferredProps'] = $deferred;
        }

        return $page;
    }

    public function render(): string
    {
        $shell = ROOT . 'app/Resources/views/app.ghost.tpl';

        return (new Template($shell, ['_wisp_page' => $this->data()]))->render();
    }

    private function requested(string $key, ?array $only): bool
    {
        return $only === null || in_array($key, $only, true);
    }

    private function url(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        if (defined('BASEPATH') && BASEPATH !== '' && str_starts_with($uri, BASEPATH)) {
            $uri = substr($uri, strlen(BASEPATH));
        }

        return '/' . ltrim($uri, '/');
    }
}

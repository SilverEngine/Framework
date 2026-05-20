<?php
declare(strict_types=1);

namespace Silver\Engine\Ghost;

use Closure;
use Silver\Http\View;

/**
 * Wisp — the server-driven page bridge baked into the Ghost engine.
 *
 * Controllers return `Wisp::render('Users/Index', [...])` (or the global
 * `wisp()` helper). Ghost renders the app shell on a full load and returns
 * the page object as JSON on subsequent navigations. No API, no client router.
 *
 * Wire protocol: Inertia (X-Inertia-* headers), so the official
 * @inertiajs/vue3 client is used unmodified.
 */
final class Wisp
{
    /**
     * Share a prop with every Wisp page (auth user, flash, errors, ...).
     * Delegates to the unified View store, so the same key is also available
     * in classic Ghost templates.
     */
    public static function share(string $key, mixed $value): void
    {
        View::share($key, $value);
    }

    /** @return array<string,mixed> */
    public static function shared(): array
    {
        return View::shared();
    }

    /**
     * Optional/lazy prop: excluded from the initial load, resolved only when
     * a partial reload requests its key.
     */
    public static function lazy(callable $callback): LazyProp
    {
        return new LazyProp(Closure::fromCallable($callback));
    }

    /**
     * Deferred prop: excluded from the initial load, advertised so the client
     * auto-fetches (and may prefetch) it after mount.
     */
    public static function defer(callable $callback, string $group = 'default'): DeferProp
    {
        return new DeferProp(Closure::fromCallable($callback), $group);
    }

    /** Asset version for the Inertia version handshake (null in dev). */
    public static function version(): ?string
    {
        return Vite::version();
    }

    public static function render(string $component, array $props = []): WispResponse
    {
        return new WispResponse($component, $props);
    }

    /**
     * Emit the Inertia root element. Called from the compiled Ghost shell
     * at execute time (see Template::parseWisp), so the per-request page
     * object is read fresh from the template's local scope.
     *
     * @param array<string,mixed> $page
     */
    public static function el(array $page): string
    {
        $json = htmlspecialchars(
            (string) json_encode($page, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ENT_QUOTES,
            'UTF-8',
        );
        // Curly braces aren't escaped by htmlspecialchars; encode them so a
        // payload containing "{{" can't ever be mis-read as a template
        // directive if the surrounding string is recompiled. Browsers
        // entity-decode data-* before exposing them to JS.
        $json = strtr($json, ['{' => '&#123;', '}' => '&#125;']);

        return '<div id="app" data-page="' . $json . '"></div>';
    }
}

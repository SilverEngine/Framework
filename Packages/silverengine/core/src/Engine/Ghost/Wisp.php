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
}

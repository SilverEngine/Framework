<?php

declare(strict_types=1);

namespace Silver\Http;

/**
 * The shared-data + composer registry for {@see View} and {@see WispResponse}.
 *
 * Used to live as `static $shared` + `static $composers` on the View class.
 * Lifting it onto a container-resolved singleton service has two wins:
 *
 *   - tests can swap a fresh instance per case via
 *     `Container::instance(ViewRegistry::class, new ViewRegistry())` instead
 *     of manually `View::flushShared()`-ing between cases
 *   - service providers can `app(ViewRegistry::class)->composer(...)` from
 *     their `before()` hook without worrying about static load order
 *
 * The static methods on View now just delegate here — backward-compatible
 * for every existing caller (`View::share`, `View::composer`, …).
 *
 * @phpstan-type ComposerEntry array{patterns:list<string>,callback:callable}
 */
final class ViewRegistry
{
    /** @var array<string,mixed> Global data merged into every view + Wisp page. */
    private array $shared = [];

    /** @var list<ComposerEntry> */
    private array $composers = [];

    /**
     * Share data globally — exposed as $key in every Ghost template and
     * as a shared prop in every Wisp page. Pass an array to share many.
     *
     * @param string|array<string,mixed> $key
     */
    public function share(string|array $key, mixed $value = null): void
    {
        foreach (is_array($key) ? $key : [$key => $value] as $k => $v) {
            $this->shared[$k] = $v;
        }
    }

    /** @return array<string,mixed> */
    public function shared(): array
    {
        return $this->shared;
    }

    /**
     * Register a composer: $callback(string $name): array runs whenever a
     * view / Wisp component matching $patterns renders, and its return
     * value is merged into that render's data.
     *
     * @param string|list<string> $patterns Exact name or fnmatch wildcard
     *                                       ("Users/*", "errors.*").
     */
    public function composer(string|array $patterns, callable $callback): void
    {
        $this->composers[] = [
            'patterns' => array_values((array) $patterns),
            'callback' => $callback,
        ];
    }

    /**
     * Resolve shared data + matching composer output for a view or Wisp
     * component name. Instance/prop data is layered on top by the caller.
     *
     * @return array<string,mixed>
     */
    public function sharedFor(string $name): array
    {
        $data = $this->shared;

        foreach ($this->composers as $composer) {
            $matches = array_any(
                $composer['patterns'],
                static fn (string $pattern): bool => $pattern === $name || fnmatch($pattern, $name),
            );
            if ($matches) {
                $data = array_merge($data, (array) ($composer['callback'])($name));
            }
        }

        return $data;
    }

    /** Reset shared data + composers (tests, long-lived processes). */
    public function reset(): void
    {
        $this->shared = [];
        $this->composers = [];
    }
}

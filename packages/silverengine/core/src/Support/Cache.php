<?php
declare(strict_types=1);

namespace Silver\Support;

use Closure;
use Silver\Orm\Cache\CacheStore;

/**
 * General-purpose cache over any {@see CacheStore} — the same backends
 * the ORM identity map uses (ArrayStore request-scope, ApcuStore
 * cross-request, or any user-supplied Redis/Memcached implementation),
 * not a parallel cache stack.
 *
 * Container singleton (driver from `config/cache.php`); reach it via
 * the `cache()` helper or the `Silver\Support\Facades\Cache` facade.
 *
 * Note: a stored literal `null` is indistinguishable from a miss
 * (CacheStore contract) — wrap in an array if you must cache null.
 */
final readonly class Cache
{
    public function __construct(private CacheStore $store) {}

    /** The underlying store, for anything not covered here. */
    public function store(): CacheStore
    {
        return $this->store;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store->get($key) ?? $default;
    }

    /**
     * @param int          $ttl  seconds; 0 = store-defined "forever"
     * @param list<string> $tags
     */
    public function put(string $key, mixed $value, int $ttl = 0, array $tags = []): void
    {
        $this->store->set($key, $value, $ttl, $tags);
    }

    public function has(string $key): bool
    {
        return $this->store->get($key) !== null;
    }

    /**
     * Get $key, or compute it via $fn, cache for $ttl and return it.
     *
     * @param Closure(): mixed $fn
     * @param list<string>     $tags
     */
    public function remember(string $key, int $ttl, Closure $fn, array $tags = []): mixed
    {
        $cached = $this->store->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $fn();
        $this->store->set($key, $value, $ttl, $tags);

        return $value;
    }

    public function forget(string $key): void
    {
        $this->store->forget($key);
    }

    /** Remove every entry stored with $tag in its tag list. */
    public function forgetTag(string $tag): void
    {
        $this->store->forgetTag($tag);
    }

    public function flush(): void
    {
        $this->store->flush();
    }
}

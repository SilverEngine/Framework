<?php
declare(strict_types=1);

namespace Silver\Orm\Cache;

/**
 * Pluggable cache backend. Implementations:
 *
 *   - ArrayStore   — per-request identity map (always-on default)
 *   - ApcuStore    — cross-request, single process
 *   - FileStore    — cross-request, cross-process, slow but portable
 *   - any user-supplied (Redis, Memcached, …)
 *
 * Tags are an OR-set: forgetTag('users') removes every entry that
 * was stored with 'users' in its tag list. Stores that can't
 * implement tagging efficiently may iterate; that's fine, tag-bust
 * is rare next to get/set.
 */
interface CacheStore
{
    public function get(string $key): mixed;

    /** @param list<string> $tags */
    public function set(string $key, mixed $value, int $ttl, array $tags = []): void;

    public function forget(string $key): void;

    public function forgetTag(string $tag): void;

    public function flush(): void;
}

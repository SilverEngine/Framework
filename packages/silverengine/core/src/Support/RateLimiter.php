<?php
declare(strict_types=1);

namespace Silver\Support;

use Closure;
use Silver\Orm\Cache\ApcuStore;
use Silver\Orm\Cache\ArrayStore;
use Silver\Orm\Cache\CacheStore;

/**
 * General sliding-window rate limiter over any {@see CacheStore}.
 *
 * Each key stores the list of hit timestamps inside the window; reads
 * prune expired hits, so limits release gradually (true sliding
 * window) instead of resetting all at once.
 *
 * The default store is APCu when enabled (cross-request behind
 * php-fpm) with a process-shared ArrayStore fallback (still correct on
 * the single-process dev server). Bind a custom CacheStore through the
 * container — Redis/Memcached — for multi-server deployments.
 * Read-modify-write on the store is best-effort, not atomic; under
 * heavy concurrency a burst may slightly exceed the limit.
 *
 * The clock is injectable for tests (seconds as float).
 */
final class RateLimiter
{
    private const string PREFIX = 'ratelimit:';

    private static ?ArrayStore $fallback = null;

    private readonly CacheStore $store;
    private readonly Closure $clock;

    public function __construct(?CacheStore $store = null, ?Closure $clock = null)
    {
        $this->store = $store ?? self::defaultStore();
        $this->clock = $clock ?? static fn (): float => microtime(true);
    }

    /** Register a hit if under the limit. True = allowed (and counted). */
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        if ($this->attempts($key, $decaySeconds) >= $maxAttempts) {
            return false;
        }
        $this->hit($key, $decaySeconds);
        return true;
    }

    /** Unconditionally record a hit. Returns the new count in the window. */
    public function hit(string $key, int $decaySeconds): int
    {
        $hits   = $this->validHits($key, $decaySeconds);
        $hits[] = ($this->clock)();
        $this->store->set(self::PREFIX . $key, $hits, $decaySeconds);
        return count($hits);
    }

    public function attempts(string $key, int $decaySeconds): int
    {
        return count($this->validHits($key, $decaySeconds));
    }

    public function remaining(string $key, int $maxAttempts, int $decaySeconds): int
    {
        return max(0, $maxAttempts - $this->attempts($key, $decaySeconds));
    }

    /** Seconds until the oldest hit in the window expires (0 = now). */
    public function availableIn(string $key, int $decaySeconds): int
    {
        $hits = $this->validHits($key, $decaySeconds);
        if ($hits === []) {
            return 0;
        }
        return max(0, (int) ceil(min($hits) + $decaySeconds - ($this->clock)()));
    }

    public function clear(string $key): void
    {
        $this->store->forget(self::PREFIX . $key);
    }

    /** @return list<float> Hit timestamps still inside the window. */
    private function validHits(string $key, int $decaySeconds): array
    {
        $raw = $this->store->get(self::PREFIX . $key);
        if (!is_array($raw)) {
            return [];
        }
        $cutoff = ($this->clock)() - $decaySeconds;
        return array_values(array_filter(
            $raw,
            static fn (mixed $t): bool => is_float($t) && $t > $cutoff,
        ));
    }

    /**
     * APCu when enabled, else one process-shared ArrayStore so every
     * RateLimiter resolved in the same process counts the same hits.
     */
    private static function defaultStore(): CacheStore
    {
        if (function_exists('apcu_fetch') && (bool) ini_get('apc.enabled')) {
            return new ApcuStore('silver:rl:');
        }
        return self::$fallback ??= new ArrayStore();
    }
}

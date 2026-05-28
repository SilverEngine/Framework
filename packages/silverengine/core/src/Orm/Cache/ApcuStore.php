<?php
declare(strict_types=1);

namespace Silver\Orm\Cache;

/**
 * Cross-request, single-process cache via APCu.
 * Falls back to a no-op when APCu isn't enabled — the model layer
 * stays functional; you just lose the persistent layer.
 */
final class ApcuStore implements CacheStore
{
    public function __construct(
        private readonly string $prefix = 'silver:orm:',
    ) {}

    public function get(string $key): mixed
    {
        if (!self::available()) {
            return null;
        }
        $ok = false;
        $value = apcu_fetch($this->prefix . $key, $ok);
        return $ok ? $value : null;
    }

    /** @param list<string> $tags */
    public function set(string $key, mixed $value, int $ttl, array $tags = []): void
    {
        if (!self::available()) {
            return;
        }
        apcu_store($this->prefix . $key, $value, $ttl);
        foreach ($tags as $tag) {
            $tagKey = $this->prefix . 'tag:' . $tag;
            $existing = apcu_fetch($tagKey) ?: [];
            $existing[] = $key;
            apcu_store($tagKey, array_values(array_unique($existing)));
        }
    }

    public function forget(string $key): void
    {
        if (!self::available()) {
            return;
        }
        apcu_delete($this->prefix . $key);
    }

    public function forgetTag(string $tag): void
    {
        if (!self::available()) {
            return;
        }
        $tagKey = $this->prefix . 'tag:' . $tag;
        $keys   = apcu_fetch($tagKey) ?: [];
        foreach ($keys as $k) {
            apcu_delete($this->prefix . $k);
        }
        apcu_delete($tagKey);
    }

    public function flush(): void
    {
        if (!self::available()) {
            return;
        }
        // Only wipe keys with our prefix — leave other APCu users alone.
        $iter = new \APCUIterator('/^' . preg_quote($this->prefix, '/') . '/');
        apcu_delete($iter);
    }

    private static function available(): bool
    {
        return function_exists('apcu_fetch') && (bool) ini_get('apc.enabled');
    }
}

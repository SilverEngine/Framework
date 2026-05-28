<?php
declare(strict_types=1);

namespace Silver\Orm\Cache;

/**
 * Per-request, in-memory cache. Always on as the identity-map layer
 * for Model::find() — calling find() twice in one request hits this,
 * not the database. Cleared between requests automatically (it's
 * just object state).
 *
 * TTL is ignored — the lifetime is the request. Tags are honoured.
 */
final class ArrayStore implements CacheStore
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @var array<string, list<string>> tag → list of keys */
    private array $tagIndex = [];

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /** @param list<string> $tags */
    public function set(string $key, mixed $value, int $ttl, array $tags = []): void
    {
        $this->data[$key] = $value;
        foreach ($tags as $tag) {
            $this->tagIndex[$tag][] = $key;
        }
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function forgetTag(string $tag): void
    {
        foreach ($this->tagIndex[$tag] ?? [] as $key) {
            unset($this->data[$key]);
        }
        unset($this->tagIndex[$tag]);
    }

    public function flush(): void
    {
        $this->data     = [];
        $this->tagIndex = [];
    }
}

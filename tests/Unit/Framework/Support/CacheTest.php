<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Support;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Cache\ArrayStore;
use Silver\Orm\Cache\CacheStore;
use Silver\Support\Cache;

/** Records every delegation so TTL/tags pass-through is provable. */
final class SpyStore implements CacheStore
{
    /** @var list<array<mixed>> */
    public array $calls = [];
    private ArrayStore $inner;

    public function __construct()
    {
        $this->inner = new ArrayStore();
    }

    public function get(string $key): mixed
    {
        $this->calls[] = ['get', $key];
        return $this->inner->get($key);
    }

    public function set(string $key, mixed $value, int $ttl, array $tags = []): void
    {
        $this->calls[] = ['set', $key, $value, $ttl, $tags];
        $this->inner->set($key, $value, $ttl, $tags);
    }

    public function forget(string $key): void
    {
        $this->calls[] = ['forget', $key];
        $this->inner->forget($key);
    }

    public function forgetTag(string $tag): void
    {
        $this->calls[] = ['forgetTag', $tag];
        $this->inner->forgetTag($tag);
    }

    public function flush(): void
    {
        $this->calls[] = ['flush'];
        $this->inner->flush();
    }
}

final class CacheTest extends TestCase
{
    private function cache(): Cache
    {
        return new Cache(new ArrayStore());
    }

    public function testPutGetRoundTrip(): void
    {
        $cache = $this->cache();
        $cache->put('answer', 42);

        self::assertSame(42, $cache->get('answer'));
    }

    public function testGetReturnsDefaultOnMiss(): void
    {
        self::assertNull($this->cache()->get('missing'));
        self::assertSame('fallback', $this->cache()->get('missing', 'fallback'));
    }

    public function testHas(): void
    {
        $cache = $this->cache();
        self::assertFalse($cache->has('k'));

        $cache->put('k', 'v');
        self::assertTrue($cache->has('k'));

        $cache->forget('k');
        self::assertFalse($cache->has('k'));
    }

    public function testRememberComputesOnceThenServesCached(): void
    {
        $cache = $this->cache();
        $computed = 0;
        $make = function () use (&$computed): string {
            $computed++;
            return 'expensive';
        };

        self::assertSame('expensive', $cache->remember('key', 60, $make));
        self::assertSame('expensive', $cache->remember('key', 60, $make));
        self::assertSame(1, $computed);
    }

    public function testRememberCachesFalsyValuesToo(): void
    {
        $cache = $this->cache();
        $computed = 0;
        $make = function () use (&$computed): int {
            $computed++;
            return 0;
        };

        self::assertSame(0, $cache->remember('zero', 60, $make));
        self::assertSame(0, $cache->remember('zero', 60, $make));
        self::assertSame(1, $computed);
    }

    public function testTtlAndTagsAreDelegatedToTheStore(): void
    {
        $spy   = new SpyStore();
        $cache = new Cache($spy);

        $cache->put('k', 'v', ttl: 120, tags: ['users', 'admin']);

        self::assertContains(['set', 'k', 'v', 120, ['users', 'admin']], $spy->calls);
    }

    public function testForgetTagBustsTaggedEntries(): void
    {
        $cache = $this->cache();
        $cache->put('a', 1, tags: ['users']);
        $cache->put('b', 2, tags: ['posts']);

        $cache->forgetTag('users');

        self::assertNull($cache->get('a'));
        self::assertSame(2, $cache->get('b'));
    }

    public function testFlushEmptiesEverything(): void
    {
        $cache = $this->cache();
        $cache->put('a', 1);
        $cache->flush();

        self::assertNull($cache->get('a'));
    }

    public function testContainerSingletonAndHelper(): void
    {
        \Silver\Core\App::refresh();

        $first = cache();
        self::assertInstanceOf(Cache::class, $first);
        self::assertSame($first, cache());

        // Facade routes to the same instance.
        \Silver\Support\Facades\Cache::put('via-facade', 'yes');
        self::assertSame('yes', $first->get('via-facade'));

        \Silver\Core\App::refresh();
    }
}

<?php
declare(strict_types=1);

namespace Silver\Orm\Cache;

use Silver\Orm\Model\Model;

/**
 * Two-layer cache for models. Both layers are key-compatible so a
 * miss on layer 1 (request-scope identity map) tries layer 2
 * (cross-request store) before falling through to a DB query.
 *
 *   Layer 1: ArrayStore         — always on, request-scoped, identity map
 *   Layer 2: CacheStore (opt-in) — set via Model::useCache()
 *
 * Key shape: "model:{FQN}:{op}:{discriminator}". Per-PK lookups use
 * "model:{FQN}:pk:{value}"; arbitrary query results use
 * "model:{FQN}:q:{hash(sql+bindings)}".
 *
 * Tagging: every entry under model M is tagged with "model:M::class"
 * so on save/delete we forgetTag() and bust everything for that
 * model in one call.
 */
final class ModelCache
{
    private static ArrayStore $identity;

    /** @var array<class-string<Model>, CacheStore> */
    private static array $stores = [];

    /** @var array<class-string<Model>, int> seconds */
    private static array $ttls = [];

    public static function identity(): ArrayStore
    {
        return self::$identity ??= new ArrayStore();
    }

    /** @param class-string<Model> $modelClass */
    public static function use(string $modelClass, CacheStore $store, int $ttl = 60): void
    {
        self::$stores[$modelClass] = $store;
        self::$ttls[$modelClass]   = $ttl;
    }

    /** @param class-string<Model> $modelClass */
    public static function ttl(string $modelClass): int
    {
        return self::$ttls[$modelClass] ?? 0;
    }

    /** @param class-string<Model> $modelClass */
    public static function store(string $modelClass): ?CacheStore
    {
        return self::$stores[$modelClass] ?? null;
    }

    /**
     * @param class-string<Model> $modelClass
     * @param callable(): ?Model  $resolver
     */
    public static function rememberPk(string $modelClass, mixed $pk, callable $resolver): ?Model
    {
        $key  = self::pkKey($modelClass, $pk);
        $hit  = self::identity()->get($key);
        if ($hit !== null) {
            return $hit;
        }
        $store = self::store($modelClass);
        if ($store instanceof CacheStore) {
            $persistent = $store->get($key);
            if ($persistent !== null) {
                self::identity()->set($key, $persistent, 0, [self::tag($modelClass)]);
                return $persistent;
            }
        }
        $fresh = $resolver();
        if ($fresh !== null) {
            self::store_($modelClass, $key, $fresh);
        }
        return $fresh;
    }

    /** @param class-string<Model> $modelClass */
    public static function forgetPk(string $modelClass, mixed $pk): void
    {
        $key = self::pkKey($modelClass, $pk);
        self::identity()->forget($key);
        self::store($modelClass)?->forget($key);
    }

    /** @param class-string<Model> $modelClass */
    public static function forgetAll(string $modelClass): void
    {
        $tag = self::tag($modelClass);
        self::identity()->forgetTag($tag);
        self::store($modelClass)?->forgetTag($tag);
    }

    /** @param class-string<Model> $modelClass */
    private static function store_(string $modelClass, string $key, Model $model): void
    {
        $tags = [self::tag($modelClass)];
        self::identity()->set($key, $model, 0, $tags);
        self::store($modelClass)?->set($key, $model, self::ttl($modelClass), $tags);
    }

    /** @param class-string<Model> $modelClass */
    public static function pkKey(string $modelClass, mixed $pk): string
    {
        $disc = is_scalar($pk) ? (string) $pk : md5(serialize($pk));
        return 'model:' . $modelClass . ':pk:' . $disc;
    }

    /** @param class-string<Model> $modelClass */
    public static function tag(string $modelClass): string
    {
        return 'model:' . $modelClass;
    }

    /** Test helper — drop the identity map (does NOT touch persistent stores). */
    public static function flushIdentity(): void
    {
        self::identity()->flush();
    }

    /** Test helper — full reset of all wiring + identity map. */
    public static function flushAll(): void
    {
        self::identity()->flush();
        foreach (self::$stores as $store) {
            $store->flush();
        }
    }
}

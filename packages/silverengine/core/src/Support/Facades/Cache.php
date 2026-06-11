<?php
declare(strict_types=1);

namespace Silver\Support\Facades;

use Silver\Support\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void  put(string $key, mixed $value, int $ttl = 0, array $tags = [])
 * @method static bool  has(string $key)
 * @method static mixed remember(string $key, int $ttl, \Closure $fn, array $tags = [])
 * @method static void  forget(string $key)
 * @method static void  forgetTag(string $tag)
 * @method static void  flush()
 *
 * @see \Silver\Support\Cache
 */
final class Cache extends Facade
{
    protected static function getClass(): string
    {
        return \Silver\Support\Cache::class;
    }
}

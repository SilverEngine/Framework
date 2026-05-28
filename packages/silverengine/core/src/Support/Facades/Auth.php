<?php
declare(strict_types=1);

namespace Silver\Support\Facades;

use Silver\Auth\AuthManager;
use Silver\Auth\Contracts\Authenticatable;
use Silver\Auth\Contracts\Guard;

/**
 * Static convenience over the default guard. Calls without a guard
 * name go through Auth::guard() → the configured `default_guard`
 * ('web' out of the box).
 *
 * @method static bool          check()
 * @method static bool          guest()
 * @method static ?Authenticatable user()
 * @method static int|string|null id()
 * @method static bool          attempt(array $credentials)
 * @method static bool          validate(array $credentials)
 * @method static void          login(Authenticatable $user)
 * @method static void          logout()
 */
final class Auth
{
    public static function guard(?string $name = null): Guard
    {
        return self::manager()->guard($name);
    }

    public static function manager(): AuthManager
    {
        return app(AuthManager::class);
    }

    public static function __callStatic(string $method, array $args): mixed
    {
        return self::guard()->{$method}(...$args);
    }
}

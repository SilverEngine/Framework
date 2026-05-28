<?php
declare(strict_types=1);

namespace Silver\Auth;

use RuntimeException;
use Silver\Auth\Contracts\Guard;
use Silver\Auth\Contracts\UserProvider;
use Silver\Auth\Providers\OrmUserProvider;
use Silver\Core\App;
use Silver\Core\Env;
use Silver\Http\Csrf\TokenStore;

/**
 * Central registry for guards + user providers.
 *
 * v1 ships one guard ('web' → SessionGuard / orm provider). The
 * indirection exists so v2 can add an api guard (Bearer tokens),
 * an ldap provider, etc. without touching the façade or callers.
 *
 * Lookup is config-driven:
 *   config/auth.php → default_guard, guards, providers
 */
class AuthManager
{
    /** @var array<string, Guard> resolved guard instances */
    private array $guards = [];

    /** @var array<string, UserProvider> resolved providers */
    private array $providers = [];

    public function guard(?string $name = null): Guard
    {
        $name ??= (string) Env::get('auth.default_guard', 'web');
        return $this->guards[$name] ??= $this->resolveGuard($name);
    }

    public function provider(?string $name = null): UserProvider
    {
        $name ??= $this->defaultProviderName();
        return $this->providers[$name] ??= $this->resolveProvider($name);
    }

    private function resolveGuard(string $name): Guard
    {
        $cfg = (array) (Env::get('auth.guards.' . $name) ?? []);
        $cfg = (array) ($cfg ?: []);

        $driver = (string) ($cfg['driver'] ?? 'session');
        $providerName = (string) ($cfg['provider'] ?? $this->defaultProviderName());
        $provider = $this->provider($providerName);

        return match ($driver) {
            'session' => new SessionGuard(
                $provider,
                App::instance()->instances()->make(TokenStore::class),
            ),
            default   => throw new RuntimeException("Unknown auth guard driver: {$driver}"),
        };
    }

    private function resolveProvider(string $name): UserProvider
    {
        $cfg = (array) (Env::get('auth.providers.' . $name) ?? []);
        $cfg = (array) ($cfg ?: []);

        $driver = (string) ($cfg['driver'] ?? 'orm');
        $model  = (string) ($cfg['model'] ?? '');
        $userField = (string) ($cfg['username_field'] ?? 'email');

        return match ($driver) {
            'orm' => new OrmUserProvider($model, $userField),
            default => throw new RuntimeException("Unknown auth provider driver: {$driver}"),
        };
    }

    private function defaultProviderName(): string
    {
        $guard = (string) Env::get('auth.default_guard', 'web');
        $cfg   = (array) (Env::get('auth.guards.' . $guard) ?? []);
        $cfg   = (array) ($cfg ?: []);
        return (string) ($cfg['provider'] ?? 'users');
    }
}

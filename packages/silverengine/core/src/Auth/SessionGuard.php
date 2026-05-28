<?php
declare(strict_types=1);

namespace Silver\Auth;

use Silver\Auth\Contracts\Authenticatable;
use Silver\Auth\Contracts\Guard;
use Silver\Auth\Contracts\UserProvider;
use Silver\Http\Csrf\TokenStore;
use Silver\Http\Session;

/**
 * Session-backed authentication guard.
 *
 * State: a single `_auth_id` key in $_SESSION. The user object is
 * resolved lazily on first user() call and memoised for the rest of
 * the request — so a controller can call Auth::user() multiple times
 * without re-hitting the database.
 *
 * On login/logout we session_regenerate_id() AND rotate the CSRF
 * token. Both are session-fixation defences: an attacker holding a
 * pre-login session ID becomes useless the moment the user
 * authenticates, and the same goes for the CSRF token they may have
 * scraped from a public page.
 */
final class SessionGuard implements Guard
{
    private const SESSION_KEY = '_auth_id';

    private ?Authenticatable $resolved = null;
    private bool $resolvedThisRequest = false;

    public function __construct(
        private readonly UserProvider $provider,
        private readonly ?TokenStore $csrf = null,
    ) {}

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?Authenticatable
    {
        if ($this->resolvedThisRequest) {
            return $this->resolved;
        }
        $this->resolvedThisRequest = true;
        $id = Session::get(self::SESSION_KEY);
        if ($id === null) {
            return $this->resolved = null;
        }
        return $this->resolved = $this->provider->retrieveById($id);
    }

    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    /** @param array<string, mixed> $credentials */
    public function attempt(array $credentials): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);
        if ($user === null) {
            return false;
        }
        if (!$this->provider->validateCredentials($user, $credentials)) {
            return false;
        }
        $this->login($user);
        return true;
    }

    /** @param array<string, mixed> $credentials */
    public function validate(array $credentials): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);
        return $user !== null
            && $this->provider->validateCredentials($user, $credentials);
    }

    public function login(Authenticatable $user): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
        Session::set(self::SESSION_KEY, $user->getAuthIdentifier());
        $this->csrf?->rotate();
        $this->resolved            = $user;
        $this->resolvedThisRequest = true;
    }

    public function logout(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_regenerate_id(true);
        }
        Session::delete(self::SESSION_KEY);
        $this->csrf?->rotate();
        $this->resolved            = null;
        $this->resolvedThisRequest = true;
    }
}

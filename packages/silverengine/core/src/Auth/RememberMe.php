<?php
declare(strict_types=1);

namespace Silver\Auth;

use Silver\Auth\Contracts\Authenticatable;
use Silver\Http\CookieJar;

/**
 * Cookie side of remember-me. Pairs a {@see RememberTokenRepository}
 * with the CookieJar: issue on login, resolve-and-ROTATE on use (a
 * stolen cookie works at most once — the legitimate user's next visit
 * invalidates it, and vice versa), revoke + clear on logout.
 */
final class RememberMe
{
    public function __construct(
        private readonly RememberTokenRepository $tokens,
        private readonly string $cookieName = 'remember_token',
        private readonly int $ttlSeconds = 2_592_000, // 30 days
    ) {}

    /** Issue a fresh token and queue its cookie. */
    public function remember(Authenticatable $user): void
    {
        $value = $this->tokens->issue($user->getAuthIdentifier(), $this->ttlSeconds);
        $this->jar()->set($this->cookieName, $value, $this->ttlSeconds);
    }

    /**
     * Authenticate the request's remember cookie. On success the used
     * token is revoked and a fresh one issued (rotate-on-use).
     */
    public function resolveUserId(): int|string|null
    {
        $value = $this->cookieValue();
        if ($value === null) {
            return null;
        }

        $userId = $this->tokens->userIdFor($value);
        if ($userId === null) {
            return null;
        }

        $this->tokens->revoke($value);
        $fresh = $this->tokens->issue($userId, $this->ttlSeconds);
        $this->jar()->set($this->cookieName, $fresh, $this->ttlSeconds);

        return $userId;
    }

    /** Revoke the presented token and clear the cookie. */
    public function forget(): void
    {
        $value = $this->cookieValue();
        if ($value !== null) {
            $this->tokens->revoke($value);
        }
        $this->jar()->delete($this->cookieName);
    }

    private function cookieValue(): ?string
    {
        $raw = $this->jar()->get($this->cookieName);
        $raw = is_array($raw) ? ($raw[0] ?? null) : $raw;
        return is_string($raw) && $raw !== '' ? $raw : null;
    }

    private function jar(): CookieJar
    {
        return app(CookieJar::class);
    }
}

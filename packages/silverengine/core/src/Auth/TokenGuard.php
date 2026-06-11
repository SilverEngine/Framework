<?php
declare(strict_types=1);

namespace Silver\Auth;

use Silver\Auth\Contracts\Authenticatable;
use Silver\Auth\Contracts\Guard;
use Silver\Auth\Contracts\UserProvider;

/**
 * Stateless bearer-token guard (the AuthManager 'token' driver).
 *
 * Reads `Authorization: Bearer <selector:validator>`, resolves the
 * token through {@see ApiTokenRepository} and the user through the
 * provider; memoised per request. {@see tokenCan()} checks the
 * presented token's abilities ('*' = everything).
 *
 * login()/logout() only pin/clear the request-local user — nothing is
 * persisted; issuing and revoking tokens is the repository's job.
 */
final class TokenGuard implements Guard
{
    private ?Authenticatable $resolved = null;
    private bool $resolvedThisRequest = false;

    /** @var list<string> */
    private array $abilities = [];

    public function __construct(
        private readonly UserProvider $provider,
        private readonly ApiTokenRepository $tokens,
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

        $bearer = $this->bearerToken();
        if ($bearer === null) {
            return $this->resolved = null;
        }
        $found = $this->tokens->find($bearer);
        if ($found === null) {
            return $this->resolved = null;
        }

        $this->abilities = $found['abilities'];
        return $this->resolved = $this->provider->retrieveById($found['user_id']);
    }

    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    /** Does the presented token carry this ability? */
    public function tokenCan(string $ability): bool
    {
        if ($this->user() === null) {
            return false;
        }
        return in_array('*', $this->abilities, true)
            || in_array($ability, $this->abilities, true);
    }

    /**
     * Stateless guard: attempt() validates and pins the user for this
     * request only — no token is minted, nothing persists.
     *
     * @param array<string, mixed> $credentials
     */
    public function attempt(array $credentials, bool $remember = false): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);
        if ($user === null || !$this->provider->validateCredentials($user, $credentials)) {
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

    public function login(Authenticatable $user, bool $remember = false): void
    {
        $this->resolved            = $user;
        $this->resolvedThisRequest = true;
    }

    public function logout(): void
    {
        $this->resolved            = null;
        $this->resolvedThisRequest = true;
        $this->abilities           = [];
    }

    private function bearerToken(): ?string
    {
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }
        $token = trim(substr($header, 7));
        return $token !== '' ? $token : null;
    }
}

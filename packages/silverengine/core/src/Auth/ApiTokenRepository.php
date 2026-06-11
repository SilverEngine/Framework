<?php
declare(strict_types=1);

namespace Silver\Auth;

use Closure;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Query\Builder;
use Silver\Orm\Query\Compiler;

/**
 * Hashed API bearer tokens with abilities — same split-token scheme
 * as {@see RememberTokenRepository}: the issued plaintext is
 * "selector:validator", shown once; only sha256(validator) is stored.
 *
 * Table (create via migration):
 *   $t->string('selector')->primary();
 *   $t->string('token_hash');
 *   $t->unsignedBigInt('user_id')->index();
 *   $t->string('name');
 *   $t->json('abilities');
 *   $t->bigInt('expires_at')->nullable();   // null = never expires
 */
final class ApiTokenRepository
{
    public function __construct(
        private readonly ConnectionManager $cm,
        private readonly string $table = 'api_tokens',
        private readonly ?Closure $clock = null,
    ) {}

    /**
     * Mint a token. Returns the plaintext — the only time it exists.
     *
     * @param list<string> $abilities '*' = everything
     */
    public function issue(int|string $userId, string $name, array $abilities = ['*'], ?int $ttlSeconds = null): string
    {
        $selector  = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));

        $this->q()->insert([
            'selector'   => $selector,
            'token_hash' => hash('sha256', $validator),
            'user_id'    => $userId,
            'name'       => $name,
            'abilities'  => json_encode(array_values($abilities), JSON_THROW_ON_ERROR),
            'expires_at' => $ttlSeconds !== null ? (int) ($this->now() + $ttlSeconds) : null,
        ]);

        return $selector . ':' . $validator;
    }

    /**
     * Resolve a presented token. Expired rows purge on sight.
     *
     * @return array{user_id: int|string, name: string, abilities: list<string>}|null
     */
    public function find(string $token): ?array
    {
        $parts = explode(':', $token, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }
        [$selector, $validator] = $parts;

        $row = $this->q()->where('selector', $selector)->first();
        if ($row === null) {
            return null;
        }
        if ($row['expires_at'] !== null && (int) $row['expires_at'] < $this->now()) {
            $this->q()->where('selector', $selector)->delete();
            return null;
        }
        if (!hash_equals((string) $row['token_hash'], hash('sha256', $validator))) {
            return null;
        }

        $abilities = json_decode((string) $row['abilities'], true);
        return [
            'user_id'   => $row['user_id'],
            'name'      => (string) $row['name'],
            'abilities' => is_array($abilities) ? array_values($abilities) : [],
        ];
    }

    public function revoke(string $token): void
    {
        $selector = explode(':', $token, 2)[0];
        if ($selector !== '') {
            $this->q()->where('selector', $selector)->delete();
        }
    }

    public function revokeAllFor(int|string $userId): void
    {
        $this->q()->where('user_id', $userId)->delete();
    }

    private function q(): Builder
    {
        return new Builder($this->cm, new Compiler($this->cm))->from($this->table);
    }

    private function now(): float
    {
        return $this->clock !== null ? ($this->clock)() : microtime(true);
    }
}

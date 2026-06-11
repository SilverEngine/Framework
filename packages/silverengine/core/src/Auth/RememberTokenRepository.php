<?php
declare(strict_types=1);

namespace Silver\Auth;

use Closure;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Query\Builder;
use Silver\Orm\Query\Compiler;

/**
 * Persistent remember-me tokens, split-token scheme:
 *
 *   cookie  = "{selector}:{validator}"
 *   db row  = selector (lookup key), sha256(validator), user_id, expires_at
 *
 * The validator never touches the database in the clear, so a leaked
 * table can't be replayed as cookies. Lookup is by selector; the
 * validator compares via hash_equals. Expired rows are purged on
 * sight.
 *
 * Table (create via migration):
 *   $t->string('selector')->primary();
 *   $t->string('token_hash');
 *   $t->unsignedBigInt('user_id')->index();
 *   $t->bigInt('expires_at');
 */
final class RememberTokenRepository
{
    public function __construct(
        private readonly ConnectionManager $cm,
        private readonly string $table = 'remember_tokens',
        private readonly ?Closure $clock = null,
    ) {}

    /** Create a token row; returns the "selector:validator" cookie value. */
    public function issue(int|string $userId, int $ttlSeconds): string
    {
        $selector  = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));

        $this->q()->insert([
            'selector'   => $selector,
            'token_hash' => hash('sha256', $validator),
            'user_id'    => $userId,
            'expires_at' => (int) ($this->now() + $ttlSeconds),
        ]);

        return $selector . ':' . $validator;
    }

    /** The user id the cookie authenticates, or null (invalid/expired). */
    public function userIdFor(string $cookieValue): int|string|null
    {
        $parts = explode(':', $cookieValue, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }
        [$selector, $validator] = $parts;

        $row = $this->q()->where('selector', $selector)->first();
        if ($row === null) {
            return null;
        }
        if ((int) $row['expires_at'] < $this->now()) {
            $this->q()->where('selector', $selector)->delete();
            return null;
        }
        return hash_equals((string) $row['token_hash'], hash('sha256', $validator))
            ? $row['user_id']
            : null;
    }

    /** Delete the row a cookie value points at (rotation / logout). */
    public function revoke(string $cookieValue): void
    {
        $selector = explode(':', $cookieValue, 2)[0];
        if ($selector !== '') {
            $this->q()->where('selector', $selector)->delete();
        }
    }

    /** Kill every device's token for a user (password change, ban…). */
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

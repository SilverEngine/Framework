<?php
declare(strict_types=1);

namespace Silver\Auth;

use Closure;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Query\Builder;
use Silver\Orm\Query\Compiler;

/**
 * Password-reset tokens — same split-token scheme as
 * {@see RememberTokenRepository} / {@see ApiTokenRepository}, keyed by
 * email instead of user id (the user may be mid-rename; the email in
 * the row is what the link was sent to).
 *
 * One active token per email: issuing a new one revokes the previous,
 * so only the latest reset mail works. Expired rows purge on sight.
 *
 * Table (create via migration):
 *   $t->string('selector')->primary();
 *   $t->string('token_hash');
 *   $t->string('email')->index();
 *   $t->bigInt('expires_at');
 */
final class PasswordResetRepository
{
    public function __construct(
        private readonly ConnectionManager $cm,
        private readonly string $table = 'password_resets',
        private readonly ?Closure $clock = null,
    ) {}

    /** Mint a token; returns the "selector:validator" plaintext (shown once). */
    public function issue(string $email, int $ttlSeconds): string
    {
        $this->revokeAllFor($email);

        $selector  = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));

        $this->q()->insert([
            'selector'   => $selector,
            'token_hash' => hash('sha256', $validator),
            'email'      => $email,
            'expires_at' => (int) ($this->now() + $ttlSeconds),
        ]);

        return $selector . ':' . $validator;
    }

    /** The email a presented token was issued for, or null (invalid/expired). */
    public function emailFor(string $token): ?string
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
        if ((int) $row['expires_at'] < $this->now()) {
            $this->q()->where('selector', $selector)->delete();
            return null;
        }
        return hash_equals((string) $row['token_hash'], hash('sha256', $validator))
            ? (string) $row['email']
            : null;
    }

    /** Delete the row a token points at (after a successful reset). */
    public function revoke(string $token): void
    {
        $selector = explode(':', $token, 2)[0];
        if ($selector !== '') {
            $this->q()->where('selector', $selector)->delete();
        }
    }

    public function revokeAllFor(string $email): void
    {
        $this->q()->where('email', $email)->delete();
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

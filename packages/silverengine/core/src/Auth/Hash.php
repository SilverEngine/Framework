<?php
declare(strict_types=1);

namespace Silver\Auth;

use Silver\Core\Env;

/**
 * Password hashing — Argon2id by default.
 *
 * One spot to set the algorithm and cost parameters; no caller has to
 * remember whether the framework standardises on bcrypt or argon. The
 * cost defaults are tuned for "fast laptop, slow attacker" (~50ms per
 * hash). Override via `config/auth.php → hashing`.
 */
final class Hash
{
    public static function make(string $plain): string
    {
        $options = self::options();
        $hashed  = password_hash($plain, $options['algo'] ?? PASSWORD_ARGON2ID, $options['opts'] ?? []);
        if (!is_string($hashed) || $hashed === '') {
            throw new \RuntimeException('Password hashing failed.');
        }
        return $hashed;
    }

    public static function check(string $plain, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }
        return password_verify($plain, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        $options = self::options();
        return password_needs_rehash($hash, $options['algo'] ?? PASSWORD_ARGON2ID, $options['opts'] ?? []);
    }

    /** @return array{algo: string|int, opts: array<string, int|string>} */
    private static function options(): array
    {
        $cfg = Env::get('auth.hashing');
        if (!is_object($cfg) && !is_array($cfg)) {
            return [
                'algo' => PASSWORD_ARGON2ID,
                'opts' => ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1],
            ];
        }
        $arr  = (array) $cfg;
        $algo = $arr['algo'] ?? PASSWORD_ARGON2ID;
        unset($arr['algo']);
        return ['algo' => $algo, 'opts' => $arr];
    }
}

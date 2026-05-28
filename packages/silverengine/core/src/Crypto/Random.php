<?php
declare(strict_types=1);

namespace Silver\Crypto;

use Silver\Support\PasswordCharset;

/**
 * Cryptographically random byte/token generation.
 *
 * - {@see token()} returns URL-safe base64 of N random bytes — the
 *   canonical CSRF / reset / verification token shape.
 * - {@see password()} draws from a {@see PasswordCharset} alphabet via
 *   `random_int` (uniform, no modulo bias).
 */
final class Random
{
    public static function token(int $bytes = 32): string
    {
        if ($bytes < 1) {
            throw new \InvalidArgumentException('Random::token() requires bytes >= 1.');
        }
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    public static function password(int $length = 16, int|PasswordCharset $charset = PasswordCharset::Symbols): string
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('Random::password() requires length >= 1.');
        }
        $alphabet = PasswordCharset::resolve($charset)->alphabet();
        $max      = strlen($alphabet) - 1;
        $out      = [];
        for ($i = 0; $i < $length; $i++) {
            $out[] = $alphabet[random_int(0, $max)];
        }
        return implode($out);
    }
}

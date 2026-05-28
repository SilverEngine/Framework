<?php
declare(strict_types=1);

namespace Silver\Http\Csrf;

use Silver\Crypto\Random;
use Silver\Http\Session;

/**
 * Per-session CSRF token store.
 *
 * The token is stable for the life of the session (so multi-tab GETs
 * keep working) and rotates on auth-state changes — login / logout
 * call {@see rotate()} to defeat session-fixation attacks against the
 * authenticated session.
 *
 * Token shape: 32 random bytes → URL-safe base64, 43 chars.
 */
final class TokenStore
{
    private const SESSION_KEY = '_csrf_token';

    public function current(): string
    {
        $existing = Session::get(self::SESSION_KEY);
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }
        return $this->generate();
    }

    public function generate(): string
    {
        $token = Random::token(32);
        Session::set(self::SESSION_KEY, $token);
        return $token;
    }

    public function rotate(): string
    {
        return $this->generate();
    }

    public function verify(string $candidate): bool
    {
        if ($candidate === '') {
            return false;
        }
        $expected = Session::get(self::SESSION_KEY);
        if (!is_string($expected) || $expected === '') {
            return false;
        }
        return hash_equals($expected, $candidate);
    }
}

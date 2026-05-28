<?php

declare(strict_types=1);

namespace Silver\Support;

/**
 * Character sets {@see \Silver\Crypto\Random::password()} can draw from.
 * Backed values match the historical integer `$charsType` argument exactly,
 * so `Random::password(16, 2)` and `Random::password(16, PasswordCharset::AlphaNumeric)`
 * are equivalent. Any unknown integer falls back to {@see self::Symbols},
 * the same lenient `default` arm the original `match` had.
 */
enum PasswordCharset: int
{
    /** Default: letters + digits + punctuation (legacy `1` / `default`). */
    case Symbols      = 1;
    case AlphaNumeric = 2;
    case Upper        = 3;
    case Alpha        = 4;
    case Numeric      = 5;

    public function alphabet(): string
    {
        return match ($this) {
            self::AlphaNumeric => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890',
            self::Upper        => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            self::Alpha        => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            self::Numeric      => '1234567890',
            self::Symbols      => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!#$%&()=?*+{}@',
        };
    }

    /**
     * Normalise the dual-typed `$charsType` argument. Unknown integers map
     * to {@see self::Symbols}, preserving the original `default` behaviour.
     */
    public static function resolve(int|self $charsType): self
    {
        return $charsType instanceof self
            ? $charsType
            : self::tryFrom($charsType) ?? self::Symbols;
    }
}

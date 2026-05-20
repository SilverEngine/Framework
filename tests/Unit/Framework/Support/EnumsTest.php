<?php

namespace Tests\Unit\Framework\Support;

use PHPUnit\Framework\TestCase;
use Silver\Support\Crypter;
use Silver\Support\LogType;
use Silver\Support\PasswordCharset;

/**
 * Locks the behaviour-preservation contract of the Step 1 enums:
 * the names list and password alphabets must match the pre-enum literals.
 */
class EnumsTest extends TestCase
{
    public function testLogTypeNamesMatchLegacyOrder(): void
    {
        $this->assertSame(
            'info, ok, warning, error, api, db, start, end, debug, normal, danger, aboard, finish, url',
            LogType::names(),
        );
    }

    public function testPasswordCharsetResolveFallsBackToSymbols(): void
    {
        $this->assertSame(PasswordCharset::Symbols, PasswordCharset::resolve(1));
        $this->assertSame(PasswordCharset::Symbols, PasswordCharset::resolve(999));
        $this->assertSame(PasswordCharset::Numeric, PasswordCharset::resolve(5));
        $this->assertSame(PasswordCharset::Upper, PasswordCharset::resolve(PasswordCharset::Upper));
    }

    public function testMakePasswordIntAndEnumAreEquivalentDomains(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9]{12}$/', Crypter::makePassword(12, 5));
        $this->assertMatchesRegularExpression('/^[0-9]{12}$/', Crypter::makePassword(12, PasswordCharset::Numeric));
        $this->assertMatchesRegularExpression('/^[A-Z]{20}$/', Crypter::makePassword(20, 3));
        $this->assertSame(16, strlen(Crypter::makePassword()));
    }
}

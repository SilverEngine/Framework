<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Crypto;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Silver\Crypto\Random;
use Silver\Support\PasswordCharset;

final class RandomTest extends TestCase
{
    public function testTokenLengthMatchesRequestedBytes(): void
    {
        // 32 bytes → 43 chars (base64 of 32 = 44, minus '=' padding)
        $this->assertSame(43, strlen(Random::token(32)));
        $this->assertSame(22, strlen(Random::token(16)));
    }

    public function testTokenAlphabetIsUrlSafe(): void
    {
        $token = Random::token(64);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $token);
    }

    public function testTokensAreUnique(): void
    {
        $set = [];
        for ($i = 0; $i < 1000; $i++) {
            $set[Random::token()] = true;
        }
        $this->assertCount(1000, $set, 'tokens collided over 1000 iterations');
    }

    public function testTokenRejectsZeroOrNegativeBytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Random::token(0);
    }

    public function testPasswordHonoursCharsetEnum(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9]{12}$/', Random::password(12, PasswordCharset::Numeric));
        $this->assertMatchesRegularExpression('/^[A-Z]{20}$/', Random::password(20, PasswordCharset::Upper));
    }

    public function testPasswordDefaultLength(): void
    {
        $this->assertSame(16, strlen(Random::password()));
    }

    public function testPasswordRejectsZeroLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Random::password(0);
    }
}

<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Http\Csrf;

use PHPUnit\Framework\TestCase;
use Silver\Http\Csrf\TokenStore;

final class TokenStoreTest extends TestCase
{
    private TokenStore $store;

    protected function setUp(): void
    {
        $_SESSION    = [];
        $this->store = new TokenStore();
    }

    public function testCurrentGeneratesAndPersistsToken(): void
    {
        $token = $this->store->current();
        $this->assertNotSame('', $token);
        $this->assertSame($token, $this->store->current(), 'current() is stable within a session');
    }

    public function testRotateGeneratesNewToken(): void
    {
        $a = $this->store->current();
        $b = $this->store->rotate();
        $this->assertNotSame($a, $b);
        $this->assertSame($b, $this->store->current());
    }

    public function testVerifyAcceptsMatchAndRejectsMismatch(): void
    {
        $token = $this->store->current();
        $this->assertTrue($this->store->verify($token));
        $this->assertFalse($this->store->verify($token . 'X'));
        $this->assertFalse($this->store->verify(''));
    }

    public function testVerifyRejectsWhenNoTokenStored(): void
    {
        $this->assertFalse($this->store->verify('any-value-without-a-session-token'));
    }

    public function testTokenIsUrlSafe(): void
    {
        $token = $this->store->generate();
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $token);
    }
}

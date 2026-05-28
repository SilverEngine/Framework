<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Crypto;

use PHPUnit\Framework\TestCase;
use Silver\Crypto\Crypter;
use Silver\Orm\Casts\EncryptedCast;

/**
 * Ensures payloads written by EncryptedCast can be read by Crypter and
 * vice versa — the cast must remain wire-compatible with the rest of
 * the framework's symmetric crypto.
 */
final class EncryptedCastInteropTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['APP_KEY'] = 'base64:' . base64_encode(random_bytes(32));
    }

    public function testCastWriteCrypterRead(): void
    {
        $cast    = new EncryptedCast();
        $written = $cast->set('payment-token-xyz');
        $this->assertSame('payment-token-xyz', Crypter::decrypt((string) $written));
    }

    public function testCrypterWriteCastRead(): void
    {
        $cast    = new EncryptedCast();
        $cipher  = Crypter::encrypt('shared-secret');
        $this->assertSame('shared-secret', $cast->get($cipher));
    }

    public function testCastPassesThroughNullAndEmpty(): void
    {
        $cast = new EncryptedCast();
        $this->assertNull($cast->set(null));
        $this->assertNull($cast->get(null));
        $this->assertNull($cast->get(''));
    }
}

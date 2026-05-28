<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Crypto;

use PHPUnit\Framework\TestCase;
use Silver\Crypto\Crypter;
use Silver\Crypto\CryptoException;

final class CrypterTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['APP_KEY'] = 'base64:' . base64_encode(random_bytes(32));
    }

    public function testRoundTrip(): void
    {
        $plain  = 'the quick brown fox jumps over the lazy dog';
        $cipher = Crypter::encrypt($plain);
        $this->assertNotSame($plain, $cipher);
        $this->assertSame($plain, Crypter::decrypt($cipher));
    }

    public function testRoundTripWithExplicitKey(): void
    {
        $plain  = 'secret payload';
        $key    = 'an-explicit-key-not-from-env';
        $cipher = Crypter::encrypt($plain, $key);
        $this->assertSame($plain, Crypter::decrypt($cipher, $key));
    }

    public function testNonceIsRandomized(): void
    {
        $plain  = 'identical';
        $a      = Crypter::encrypt($plain);
        $b      = Crypter::encrypt($plain);
        $this->assertNotSame($a, $b, 'GCM nonce should randomize output for same input');
    }

    public function testTamperingTagFailsAuthentication(): void
    {
        $cipher = Crypter::encrypt('honest payload');
        $bytes  = base64_decode($cipher, strict: true);
        // Flip a bit inside the auth tag (bytes 12..27).
        $bytes[20] = chr(ord($bytes[20]) ^ 0x01);
        $tampered  = base64_encode($bytes);

        $this->expectException(CryptoException::class);
        Crypter::decrypt($tampered);
    }

    public function testTamperingCiphertextFailsAuthentication(): void
    {
        $cipher = Crypter::encrypt('honest payload');
        $bytes  = base64_decode($cipher, strict: true);
        // Flip a bit inside the ciphertext (offset >= 28).
        $bytes[30] = chr(ord($bytes[30]) ^ 0x01);
        $tampered  = base64_encode($bytes);

        $this->expectException(CryptoException::class);
        Crypter::decrypt($tampered);
    }

    public function testMalformedPayloadIsRejected(): void
    {
        $this->expectException(CryptoException::class);
        Crypter::decrypt('not-real-ciphertext');
    }

    public function testSignAndVerify(): void
    {
        $msg = 'reset:user:42';
        $sig = Crypter::sign($msg);
        $this->assertTrue(Crypter::verify($msg, $sig));
    }

    public function testVerifyRejectsTamperedSignature(): void
    {
        $msg = 'reset:user:42';
        $sig = Crypter::sign($msg);
        $this->assertFalse(Crypter::verify($msg . 'X', $sig));
        $this->assertFalse(Crypter::verify($msg, $sig . 'A'));
    }

    public function testSignIsStable(): void
    {
        $msg = 'same-input';
        $this->assertSame(Crypter::sign($msg), Crypter::sign($msg));
    }

    public function testRawAppKeyAlsoWorks(): void
    {
        $_ENV['APP_KEY'] = 'mysupersecurekey'; // legacy non-base64 string
        $plain  = 'works either way';
        $cipher = Crypter::encrypt($plain);
        $this->assertSame($plain, Crypter::decrypt($cipher));
    }

    public function testEmptyAppKeyThrowsHelpfulError(): void
    {
        $_ENV['APP_KEY'] = '';
        $this->expectException(CryptoException::class);
        $this->expectExceptionMessageMatches('/key:generate/');
        Crypter::encrypt('x');
    }
}

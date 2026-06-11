<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Http;

use PHPUnit\Framework\TestCase;
use Silver\Http\Cookie;
use Silver\Http\CookieJar;

/**
 * Read-path coverage only — the write path goes through
 * Response::setCookie / setcookie(), which needs a SAPI.
 */
final class CookieJarTest extends TestCase
{
    protected function setUp(): void
    {
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
    }

    public function testGetDecodesPlaintextEnvelope(): void
    {
        $_COOKIE['prefs'] = json_encode([
            'values'    => ['dark', 'compact'],
            'expire'    => 9999999999,
            'encrypted' => false,
        ]);

        $this->assertTrue(Cookie::exists('prefs'));
        $this->assertSame(['dark', 'compact'], Cookie::get('prefs'));
        $this->assertSame(9999999999, Cookie::get('prefs', 'expire'));
        $this->assertFalse(Cookie::isCrypted('prefs'));
    }

    public function testMalformedCiphertextIsNotTreatedAsPlaintext(): void
    {
        $_COOKIE['evil'] = 'sec:not-real-ciphertext';

        $this->assertTrue(Cookie::isCrypted('evil'));
        $this->assertNull(Cookie::get('evil'));
    }

    public function testMissingCookieReturnsNull(): void
    {
        $this->assertFalse(Cookie::exists('nope'));
        $this->assertNull(Cookie::get('nope'));
    }

    public function testFacadeAndJarShareTheSingleton(): void
    {
        $this->assertSame(app(CookieJar::class), app(CookieJar::class));
        $this->assertSame($_COOKIE, Cookie::all());
    }
}

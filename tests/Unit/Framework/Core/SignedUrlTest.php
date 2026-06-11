<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Core;

use PHPUnit\Framework\TestCase;
use Silver\Helpers\URL;
use Silver\Http\Request;

final class SignedUrlTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['APP_KEY'] = 'base64:' . base64_encode(str_repeat('k', 32));
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testSignedUrlCarriesAVerifiableSignature(): void
    {
        $url = URL::signed('/unsubscribe', ['user' => 42]);

        self::assertStringContainsString('/unsubscribe?user=42&signature=', $url);
        self::assertTrue(URL::hasValidSignature($url));
    }

    public function testSignatureIsUrlSafe(): void
    {
        $url = URL::signed('/verify-email', ['user' => 7, 'hash' => 'abc']);

        $sig = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $sig);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', (string) $sig['signature']);
    }

    public function testTamperedParamFailsVerification(): void
    {
        $url = URL::signed('/unsubscribe', ['user' => 42]);

        self::assertFalse(URL::hasValidSignature(str_replace('user=42', 'user=43', $url)));
    }

    public function testTamperedPathFailsVerification(): void
    {
        $url = URL::signed('/unsubscribe', ['user' => 42]);

        self::assertFalse(URL::hasValidSignature(str_replace('/unsubscribe', '/admin', $url)));
    }

    public function testAddedParamFailsVerification(): void
    {
        $url = URL::signed('/download', ['file' => 9]);

        self::assertFalse(URL::hasValidSignature($url . '&extra=1'));
    }

    public function testMissingSignatureFails(): void
    {
        self::assertFalse(URL::hasValidSignature('/unsubscribe?user=42'));
    }

    public function testQueryAlreadyInPathIsSigned(): void
    {
        $url = URL::signed('/report?year=2026', ['month' => 6]);

        self::assertTrue(URL::hasValidSignature($url));
        self::assertFalse(URL::hasValidSignature(str_replace('year=2026', 'year=2025', $url)));
    }

    public function testTemporarySignedUrlExpires(): void
    {
        $fresh = URL::temporarySigned('/reset-password', 3600, ['user' => 1]);
        self::assertTrue(URL::hasValidSignature($fresh));
        self::assertStringContainsString('expires=', $fresh);

        $expired = URL::signed('/reset-password', ['user' => 1], expiresAt: time() - 10);
        self::assertFalse(URL::hasValidSignature($expired));
    }

    public function testExpiryIsCoveredByTheSignature(): void
    {
        $expired = URL::signed('/reset-password', ['user' => 1], expiresAt: time() - 10);

        // Attacker pushes 'expires' into the future — signature must break.
        $forged = (string) preg_replace('/expires=\d+/', 'expires=' . (time() + 3600), $expired);
        self::assertFalse(URL::hasValidSignature($forged));
    }

    public function testRequestObjectVerification(): void
    {
        $url = URL::signed('/unsubscribe', ['user' => 42]);

        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $_SERVER['REQUEST_URI'] = $url;
        $_GET = $query;

        self::assertTrue(URL::hasValidSignature(new Request()));

        $_GET['user'] = '43';
        self::assertFalse(URL::hasValidSignature(new Request()));
    }

    public function testDifferentKeyFailsVerification(): void
    {
        $url = URL::signed('/unsubscribe', ['user' => 42]);

        $_ENV['APP_KEY'] = 'base64:' . base64_encode(str_repeat('x', 32));
        self::assertFalse(URL::hasValidSignature($url));
    }
}

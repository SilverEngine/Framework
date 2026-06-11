<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Http;

use PHPUnit\Framework\TestCase;
use Silver\Http\Middleware\SecurityHeaders;
use Silver\Http\Request;
use Silver\Http\Response;

final class SecurityHeadersMiddlewareTest extends TestCase
{
    private Response $res;

    protected function setUp(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/';
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);

        $this->res = new Response();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
    }

    private function handle(?array $config = []): mixed
    {
        return new SecurityHeaders($config)->execute(
            new Request(),
            $this->res,
            fn (): string => 'body',
        );
    }

    public function testSaneDefaultsAreEmitted(): void
    {
        $out = $this->handle();

        self::assertSame('body', $out);
        self::assertSame('SAMEORIGIN', $this->res->getHeader('X-Frame-Options'));
        self::assertSame('nosniff', $this->res->getHeader('X-Content-Type-Options'));
        self::assertSame('strict-origin-when-cross-origin', $this->res->getHeader('Referrer-Policy'));
    }

    public function testCspAndPermissionsPolicyOffByDefault(): void
    {
        $this->handle();

        self::assertNull($this->res->getHeader('Content-Security-Policy'));
        self::assertNull($this->res->getHeader('Permissions-Policy'));
    }

    public function testConfiguredCspIsEmitted(): void
    {
        $this->handle(['content-security-policy' => "default-src 'self'"]);

        self::assertSame("default-src 'self'", $this->res->getHeader('Content-Security-Policy'));
    }

    public function testNullValueDisablesADefaultHeader(): void
    {
        $this->handle(['x-frame-options' => null]);

        self::assertNull($this->res->getHeader('X-Frame-Options'));
        self::assertSame('nosniff', $this->res->getHeader('X-Content-Type-Options'));
    }

    public function testCustomValueOverridesDefault(): void
    {
        $this->handle(['x-frame-options' => 'DENY']);

        self::assertSame('DENY', $this->res->getHeader('X-Frame-Options'));
    }

    public function testHstsOmittedOnPlainHttp(): void
    {
        $this->handle();

        self::assertNull($this->res->getHeader('Strict-Transport-Security'));
    }

    public function testHstsEmittedOnHttpsWithDefaults(): void
    {
        $_SERVER['HTTPS'] = 'on';

        $this->handle();

        self::assertSame(
            'max-age=31536000; includeSubDomains',
            $this->res->getHeader('Strict-Transport-Security'),
        );
    }

    public function testHstsHonoursForwardedProtoAndPreload(): void
    {
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

        $this->handle(['hsts' => ['max-age' => 600, 'include-subdomains' => false, 'preload' => true]]);

        self::assertSame(
            'max-age=600; preload',
            $this->res->getHeader('Strict-Transport-Security'),
        );
    }

    public function testHstsCanBeDisabled(): void
    {
        $_SERVER['HTTPS'] = 'on';

        $this->handle(['hsts' => ['enabled' => false]]);

        self::assertNull($this->res->getHeader('Strict-Transport-Security'));
    }

    public function testAlreadySetResponseHeaderIsNotClobbered(): void
    {
        // A controller / earlier middleware set a per-route CSP.
        $this->res->setHeader('Content-Security-Policy', "default-src 'none'");

        $this->handle(['content-security-policy' => "default-src 'self'"]);

        self::assertSame("default-src 'none'", $this->res->getHeader('Content-Security-Policy'));
    }

    public function testRequestIsSecureDetection(): void
    {
        self::assertFalse(new Request()->isSecure());

        $_SERVER['HTTPS'] = 'on';
        self::assertTrue(new Request()->isSecure());

        unset($_SERVER['HTTPS']);
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        self::assertTrue(new Request()->isSecure());
    }
}

<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Http;

use PHPUnit\Framework\TestCase;
use Silver\Http\Middleware\Cors;
use Silver\Http\Request;
use Silver\Http\Response;

final class CorsMiddlewareTest extends TestCase
{
    private Response $res;
    private bool $nextCalled = false;

    protected function setUp(): void
    {
        $_GET = $_POST = $_REQUEST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/api/users';
        $_SERVER['HTTP_ORIGIN']    = 'https://app.example.com';
        unset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']);

        $this->res        = new Response();
        $this->nextCalled = false;
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']);
    }

    private function handle(array $config): mixed
    {
        $mw = new Cors($config + ['paths' => ['/api/*']]);
        return $mw->execute(new Request(), $this->res, function (): string {
            $this->nextCalled = true;
            return 'body';
        });
    }

    public function testWildcardOriginEmitsLiteralStar(): void
    {
        $out = $this->handle(['allowed_origins' => ['*']]);

        self::assertSame('body', $out);
        self::assertTrue($this->nextCalled);
        self::assertSame('*', $this->res->getHeader('Access-Control-Allow-Origin'));
    }

    public function testListedOriginIsEchoedWithVary(): void
    {
        $this->handle(['allowed_origins' => ['https://app.example.com']]);

        self::assertSame('https://app.example.com', $this->res->getHeader('Access-Control-Allow-Origin'));
        self::assertSame('Origin', $this->res->getHeader('Vary'));
    }

    public function testUnlistedOriginGetsNoCorsHeadersButRequestProceeds(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.example.net';

        $out = $this->handle(['allowed_origins' => ['https://app.example.com']]);

        self::assertSame('body', $out);
        self::assertTrue($this->nextCalled);
        self::assertNull($this->res->getHeader('Access-Control-Allow-Origin'));
    }

    public function testWildcardSubdomainPatternMatches(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://staging.example.com';

        $this->handle(['allowed_origins' => ['https://*.example.com']]);

        self::assertSame('https://staging.example.com', $this->res->getHeader('Access-Control-Allow-Origin'));
    }

    public function testCredentialsEchoOriginInsteadOfStar(): void
    {
        $this->handle(['allowed_origins' => ['*'], 'supports_credentials' => true]);

        self::assertSame('https://app.example.com', $this->res->getHeader('Access-Control-Allow-Origin'));
        self::assertSame('true', $this->res->getHeader('Access-Control-Allow-Credentials'));
    }

    public function testExposedHeadersAreEmitted(): void
    {
        $this->handle(['allowed_origins' => ['*'], 'exposed_headers' => ['X-Total-Count', 'X-Request-Id']]);

        self::assertSame(
            'X-Total-Count, X-Request-Id',
            $this->res->getHeader('Access-Control-Expose-Headers'),
        );
    }

    public function testPreflightShortCircuitsWith204(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'PUT';

        $out = $this->handle([
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'PUT'],
            'allowed_headers' => ['Content-Type', 'X-Custom'],
            'max_age'         => 3600,
        ]);

        self::assertFalse($this->nextCalled);
        self::assertNull($out);
        self::assertSame(204, $this->res->getCode());
        self::assertSame('*', $this->res->getHeader('Access-Control-Allow-Origin'));
        self::assertSame('GET, PUT', $this->res->getHeader('Access-Control-Allow-Methods'));
        self::assertSame('Content-Type, X-Custom', $this->res->getHeader('Access-Control-Allow-Headers'));
        self::assertSame('3600', $this->res->getHeader('Access-Control-Max-Age'));
    }

    public function testPreflightFromUnlistedOriginStays403Free(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'PUT';
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.example.net';

        $out = $this->handle(['allowed_origins' => ['https://app.example.com']]);

        // No CORS headers — the browser blocks it; we don't error.
        self::assertNull($this->res->getHeader('Access-Control-Allow-Origin'));
        self::assertFalse($this->nextCalled);
        self::assertNull($out);
        self::assertSame(204, $this->res->getCode());
    }

    public function testNonMatchingPathIsUntouched(): void
    {
        $_SERVER['REQUEST_URI'] = '/web/page';

        $out = $this->handle(['allowed_origins' => ['*']]);

        self::assertSame('body', $out);
        self::assertTrue($this->nextCalled);
        self::assertNull($this->res->getHeader('Access-Control-Allow-Origin'));
    }

    public function testRequestWithoutOriginIsUntouched(): void
    {
        unset($_SERVER['HTTP_ORIGIN']);

        $out = $this->handle(['allowed_origins' => ['*']]);

        self::assertSame('body', $out);
        self::assertNull($this->res->getHeader('Access-Control-Allow-Origin'));
    }

    public function testWildcardMethodsEchoRequestedMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'DELETE';

        $this->handle(['allowed_origins' => ['*'], 'allowed_methods' => ['*']]);

        self::assertSame('DELETE', $this->res->getHeader('Access-Control-Allow-Methods'));
    }
}

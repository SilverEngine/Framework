<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Http\Csrf;

use PHPUnit\Framework\TestCase;
use Silver\Http\Csrf\CsrfTokenMismatchException;
use Silver\Http\Csrf\TokenStore;
use Silver\Http\Middleware\VerifyCsrfToken;
use Silver\Http\Request;
use Silver\Http\Response;

final class VerifyCsrfTokenTest extends TestCase
{
    private TokenStore $store;
    private VerifyCsrfToken $mw;
    private Request $req;
    private Response $res;

    protected function setUp(): void
    {
        $_SESSION  = [];
        $_REQUEST  = [];
        $_POST     = [];
        $_GET      = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset(
            $_SERVER['HTTP_X_XSRF_TOKEN'],
            $_SERVER['HTTP_X_CSRF_TOKEN'],
            $_SERVER['HTTP_X_INERTIA'],
            $_SERVER['HTTP_ACCEPT'],
        );

        $this->store = new TokenStore();
        $this->mw    = new VerifyCsrfToken($this->store, [
            'cookie_name'  => 'XSRF-TOKEN',
            'header_names' => ['X-XSRF-TOKEN', 'X-CSRF-TOKEN'],
            'field_name'   => '_token',
            'except'       => [],
        ]);
        $this->req   = new Request();
        $this->res   = new Response();
    }

    public function testGetRequestSkipsVerification(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->req = new Request();

        $ran = false;
        $this->mw->execute($this->req, $this->res, function () use (&$ran) {
            $ran = true;
            return 'ok';
        });
        $this->assertTrue($ran);
    }

    public function testPostWithoutTokenThrows419(): void
    {
        $this->expectException(CsrfTokenMismatchException::class);
        $this->mw->execute($this->req, $this->res, fn () => 'unreached');
    }

    public function testPostWithMatchingHeaderTokenPasses(): void
    {
        $token = $this->store->current();
        $_SERVER['HTTP_X_XSRF_TOKEN'] = $token;

        $ran = false;
        $this->mw->execute($this->req, $this->res, function () use (&$ran) {
            $ran = true;
            return 'ok';
        });
        $this->assertTrue($ran);
    }

    public function testPostWithLegacyCsrfHeaderPasses(): void
    {
        $token = $this->store->current();
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;

        $ran = false;
        $this->mw->execute($this->req, $this->res, function () use (&$ran) {
            $ran = true;
            return 'ok';
        });
        $this->assertTrue($ran);
    }

    public function testPostWithMatchingFormFieldPasses(): void
    {
        $token = $this->store->current();
        $_POST['_token']    = $token;
        $_REQUEST['_token'] = $token;

        $ran = false;
        $this->mw->execute($this->req, $this->res, function () use (&$ran) {
            $ran = true;
            return 'ok';
        });
        $this->assertTrue($ran);
    }

    public function testPostWithMismatchedTokenThrows(): void
    {
        $this->store->current();
        $_POST['_token']    = 'definitely-wrong';
        $_REQUEST['_token'] = 'definitely-wrong';

        $this->expectException(CsrfTokenMismatchException::class);
        $this->mw->execute($this->req, $this->res, fn () => 'unreached');
    }

    public function testExceptPatternBypassesVerification(): void
    {
        $mw = new VerifyCsrfToken($this->store, [
            'cookie_name'  => 'XSRF-TOKEN',
            'header_names' => ['X-XSRF-TOKEN'],
            'field_name'   => '_token',
            'except'       => ['/api/webhooks/*'],
        ]);
        $_SERVER['REQUEST_URI'] = '/api/webhooks/stripe';
        $this->req = new Request();

        $ran = false;
        $mw->execute($this->req, $this->res, function () use (&$ran) {
            $ran = true;
            return 'ok';
        });
        $this->assertTrue($ran);
    }

    public function testMiddlewareSetsXsrfTokenCookie(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->req = new Request();

        $this->mw->execute($this->req, $this->res, fn () => 'ok');

        $reflector = new \ReflectionClass($this->res);
        $prop      = $reflector->getProperty('cookies');
        $cookies   = $prop->getValue($this->res);
        $this->assertArrayHasKey('XSRF-TOKEN', $cookies);
        $this->assertSame($this->store->current(), $cookies['XSRF-TOKEN'][0]);
    }
}

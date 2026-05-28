<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Http;

use PHPUnit\Framework\TestCase;
use Silver\App\Middlewares\ErrorHandler as ErrorHandlerMiddleware;
use Silver\Core\App;
use Silver\Core\ErrorHandler as Handler;
use Silver\Http\AuthorizationException;
use Silver\Http\Request;
use Silver\Http\Response;
use Silver\Http\ValidationException;

/**
 * The middleware turns ValidationException / AuthorizationException
 * into JSON for JSON / Wisp / AJAX clients, or flashes errors+old and
 * sets a 302 Location for classic HTML form posts.
 */
final class ErrorHandlerValidationTest extends TestCase
{
    private ErrorHandlerMiddleware $mw;
    private Request $req;
    private Response $res;

    protected function setUp(): void
    {
        $handler = App::instance()->instances()->make(Handler::class);
        $this->mw  = new ErrorHandlerMiddleware($handler);
        $this->req = new Request();
        $this->res = new Response();

        $_SESSION = [];
        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_INERTIA'], $_SERVER['HTTP_X_REQUESTED_WITH'], $_SERVER['HTTP_REFERER']);
    }

    public function testJsonClientGetsJsonEnvelope(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $body = $this->mw->execute(
            $this->req,
            $this->res,
            fn () => throw new ValidationException(['email' => ['email is required.']], ['email' => ''])
        );

        $this->assertSame(422, $this->res->getCode());
        $this->assertSame('application/json; charset=utf-8', $this->res->getHeader('Content-Type'));
        $decoded = json_decode((string) $body, true);
        $this->assertArrayHasKey('errors', $decoded);
        $this->assertSame(['email is required.'], $decoded['errors']['email']);
    }

    public function testInertiaClientGetsJsonEnvelope(): void
    {
        $_SERVER['HTTP_X_INERTIA'] = 'true';

        $body = $this->mw->execute(
            $this->req,
            $this->res,
            fn () => throw new ValidationException(['name' => ['KEY is required.']])
        );

        $this->assertSame(422, $this->res->getCode());
        $this->assertStringContainsString('application/json', (string) $this->res->getHeader('Content-Type'));
    }

    public function testHtmlClientGetsRedirectAndFlash(): void
    {
        $_SERVER['HTTP_REFERER'] = '/register';
        $_SERVER['HTTP_ACCEPT']  = 'text/html';

        $body = $this->mw->execute(
            $this->req,
            $this->res,
            fn () => throw new ValidationException(
                ['email' => ['email is required.']],
                ['email' => ''],
            ),
        );

        $this->assertSame(302, $this->res->getCode());
        $this->assertSame('/register', $this->res->getHeader('Location'));
        $this->assertSame('', $body);

        // Flash is stored under a key marked for next-request consumption.
        // We don't introspect Session internals here — just confirm $_SESSION
        // received the flashed map.
        $found = false;
        foreach ($_SESSION as $entry) {
            if (is_array($entry) && isset($entry['_errors']['email'])) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'expected _errors to be flashed to session');
    }

    public function testHtmlClientWithoutReferrerRedirectsToRoot(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $this->mw->execute(
            $this->req,
            $this->res,
            fn () => throw new ValidationException(['x' => ['nope']]),
        );

        $this->assertSame('/', $this->res->getHeader('Location'));
    }

    public function testAuthorizationExceptionJsonClient(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $body = $this->mw->execute(
            $this->req,
            $this->res,
            fn () => throw new AuthorizationException('nope'),
        );

        $this->assertSame(403, $this->res->getCode());
        $decoded = json_decode((string) $body, true);
        $this->assertSame('nope', $decoded['message']);
    }
}

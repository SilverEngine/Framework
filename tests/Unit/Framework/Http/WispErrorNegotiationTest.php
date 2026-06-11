<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Http;

use PHPUnit\Framework\TestCase;
use Silver\App\Middlewares\ErrorHandler as ErrorHandlerMiddleware;
use Silver\Auth\AuthenticationException;
use Silver\Core\ErrorHandler;
use Silver\Http\Request;
use Silver\Http\Response;
use Silver\Http\ValidationException;

/**
 * Wisp polish — the error middleware must speak Inertia, not generic
 * JSON, when X-Inertia is present: validation flashes + redirects
 * back (303) so useForm picks errors up from the shared prop, and
 * authentication redirects to login so the client follows.
 */
final class WispErrorNegotiationTest extends TestCase
{
    private Response $res;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_GET = $_POST = $_REQUEST = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/profile';
        $_SERVER['HTTP_REFERER']   = '/profile/edit';
        unset($_SERVER['HTTP_X_INERTIA'], $_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH']);

        $this->res = new Response();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_INERTIA'], $_SERVER['HTTP_REFERER'], $_SERVER['HTTP_ACCEPT']);
    }

    private function mw(): ErrorHandlerMiddleware
    {
        return new ErrorHandlerMiddleware(app(ErrorHandler::class));
    }

    public function testWispValidationFlashesAndRedirectsBack303(): void
    {
        $_SERVER['HTTP_X_INERTIA'] = 'true';

        $this->mw()->execute(new Request(), $this->res, function (): never {
            throw new ValidationException(['email' => ['email is required.']], ['email' => '']);
        });

        self::assertSame(303, $this->res->getCode());
        self::assertSame('/profile/edit', $this->res->getHeader('Location'));
        self::assertSame(['email' => ['email is required.']], $_SESSION['flash']['_errors'] ?? null);
        self::assertSame(['email' => ''], $_SESSION['flash']['_old'] ?? null);
    }

    public function testPlainJsonClientStillGets422Envelope(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $out = $this->mw()->execute(new Request(), $this->res, function (): never {
            throw new ValidationException(['email' => ['email is required.']]);
        });

        self::assertSame(422, $this->res->getCode());
        $decoded = json_decode((string) $out, true);
        self::assertSame(['email' => ['email is required.']], $decoded['errors']);
    }

    public function testClassicFormStillFlashesAndRedirects302(): void
    {
        $this->mw()->execute(new Request(), $this->res, function (): never {
            throw new ValidationException(['email' => ['email is required.']]);
        });

        self::assertSame(302, $this->res->getCode());
        self::assertSame('/profile/edit', $this->res->getHeader('Location'));
    }

    public function testWispAuthenticationRedirectsToLoginInsteadOf401Json(): void
    {
        $_SERVER['HTTP_X_INERTIA'] = 'true';

        $this->mw()->execute(new Request(), $this->res, function (): never {
            throw new AuthenticationException();
        });

        self::assertSame(302, $this->res->getCode());
        self::assertNotNull($this->res->getHeader('Location'));
    }
}

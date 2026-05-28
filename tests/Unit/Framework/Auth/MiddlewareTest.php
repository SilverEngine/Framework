<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Auth;

use PHPUnit\Framework\TestCase;
use Silver\Auth\AuthenticationException;
use Silver\Auth\AuthManager;
use Silver\Auth\Middleware\Authenticate;
use Silver\Auth\Middleware\Guest;
use Silver\Auth\Middleware\Throttle;
use Silver\Auth\Providers\OrmUserProvider;
use Silver\Auth\SessionGuard;
use Silver\Auth\ThrottleRequestsException;
use Silver\Http\Csrf\TokenStore;
use Silver\Http\Request;
use Silver\Http\Response;
use Silver\Orm\Cache\ModelCache;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Model\AttributeRegistry;
use Silver\Orm\Model\EventDispatcher;
use Silver\Orm\Model\Model;
use Tests\Unit\Framework\Auth\fixtures\AuthUser;

final class MiddlewareTest extends TestCase
{
    private SessionGuard $guard;
    private AuthManager $manager;
    private Request $req;
    private Response $res;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_REQUEST = [];
        $_POST    = [];
        $_GET     = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_INERTIA']);

        AttributeRegistry::flush();
        ModelCache::flushAll();
        $cm = new ConnectionManager();
        $cm->connect('default', 'sqlite::memory:');
        $cm->setDefault('default');
        $cm->exec(
            'CREATE TABLE auth_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL)'
        );
        Model::bind($cm, new EventDispatcher());
        AuthUser::create(['email' => 'a@b.test', 'password' => \Silver\Auth\Hash::make('pw1234')]);

        $provider = new OrmUserProvider(AuthUser::class, 'email');
        $this->guard = new SessionGuard($provider, new TokenStore());

        // Stub a minimal AuthManager that returns our pre-built guard,
        // sidestepping config-driven resolution for the unit test.
        $this->manager = new class($this->guard) extends AuthManager {
            public function __construct(private readonly SessionGuard $g) {}
            public function guard(?string $name = null): \Silver\Auth\Contracts\Guard
            {
                return $this->g;
            }
        };

        $this->req = new Request();
        $this->res = new Response();
    }

    public function testAuthenticateRejectsGuestWith401(): void
    {
        $mw = new Authenticate($this->manager);
        $this->expectException(AuthenticationException::class);
        $mw->execute($this->req, $this->res, fn () => 'unreached');
    }

    public function testAuthenticateAllowsLoggedInUser(): void
    {
        $this->guard->login(AuthUser::find(1));
        $mw = new Authenticate($this->manager);
        $this->assertSame('ok', $mw->execute($this->req, $this->res, fn () => 'ok'));
    }

    public function testGuestMiddlewareRedirectsAuthenticatedUser(): void
    {
        $this->guard->login(AuthUser::find(1));
        $mw = new Guest($this->manager);
        $body = $mw->execute($this->req, $this->res, fn () => 'unreached');

        $this->assertSame(302, $this->res->getCode());
        $this->assertSame('', $body);
        $this->assertNotNull($this->res->getHeader('Location'));
    }

    public function testGuestMiddlewareAllowsActualGuest(): void
    {
        $mw = new Guest($this->manager);
        $this->assertSame('ok', $mw->execute($this->req, $this->res, fn () => 'ok'));
    }

    public function testThrottleAllowsBelowLimit(): void
    {
        $mw = new Throttle(maxAttempts: 3, decaySeconds: 60);
        $this->assertSame('ok', $mw->execute($this->req, $this->res, fn () => 'ok'));
        $this->assertSame('ok', $mw->execute($this->req, $this->res, fn () => 'ok'));
    }

    public function testThrottleRejectsAtLimit(): void
    {
        $mw = new Throttle(maxAttempts: 2, decaySeconds: 60);
        $mw->execute($this->req, $this->res, fn () => 'ok');
        $mw->execute($this->req, $this->res, fn () => 'ok');
        $this->expectException(ThrottleRequestsException::class);
        $mw->execute($this->req, $this->res, fn () => 'ok');
    }

    public function testThrottleResetsAfterWindow(): void
    {
        $mw = new Throttle(maxAttempts: 1, decaySeconds: 60);
        $mw->execute($this->req, $this->res, fn () => 'ok');

        // Manually expire the throttle bucket.
        foreach ($_SESSION['_auth_throttle'] as $k => &$v) {
            $v['reset'] = time() - 1;
        }
        unset($v);

        $this->assertSame('ok', $mw->execute($this->req, $this->res, fn () => 'ok'));
    }
}

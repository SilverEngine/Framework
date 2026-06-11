<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Core;

use PHPUnit\Framework\TestCase;
use Silver\Core\Route;

/**
 * F2 — per-route middleware. Routes carry a middleware LIST (string
 * input normalized, legacy 'public' visibility tag dropped), groups
 * propagate middleware onto routes registered inside them, and the
 * route cache round-trips the list.
 */
final class RouteMiddlewareTest extends TestCase
{
    private Route $router;

    protected function setUp(): void
    {
        $this->router = new Route();
    }

    public function testArrayMiddlewareIsStored(): void
    {
        $this->router->get('/admin', 'Admin@index', 'admin', ['auth', 'throttle:60,1']);

        $route = $this->router->find('/admin', 'get');
        self::assertSame(['auth', 'throttle:60,1'], $route->middleware());
    }

    public function testStringMiddlewareNormalizedToList(): void
    {
        $this->router->get('/profile', 'Profile@show', null, 'auth');

        $route = $this->router->find('/profile', 'get');
        self::assertSame(['auth'], $route->middleware());
    }

    public function testLegacyPublicTagIsDropped(): void
    {
        $this->router->get('/landing', 'Landing@show', null, 'public');

        $route = $this->router->find('/landing', 'get');
        self::assertSame([], $route->middleware());
    }

    public function testDefaultIsEmptyList(): void
    {
        $this->router->get('/plain', 'Plain@show');

        $route = $this->router->find('/plain', 'get');
        self::assertSame([], $route->middleware());
    }

    public function testGroupMiddlewareInheritedByRoutes(): void
    {
        $this->router->group(['prefix' => 'api', 'middleware' => ['throttle:60,1']], function (): void {
            $this->router->get('/users', 'Users@index', null, ['auth']);
            $this->router->get('/ping', 'Ping@show');
        });

        self::assertSame(
            ['throttle:60,1', 'auth'],
            $this->router->find('/api/users', 'get')->middleware(),
        );
        self::assertSame(
            ['throttle:60,1'],
            $this->router->find('/api/ping', 'get')->middleware(),
        );
    }

    public function testNestedGroupsStackMiddleware(): void
    {
        $this->router->group(['middleware' => ['web']], function (): void {
            $this->router->group(['middleware' => ['auth']], function (): void {
                $this->router->get('/dash', 'Dash@show');
            });
            $this->router->get('/open', 'Open@show');
        });

        self::assertSame(['web', 'auth'], $this->router->find('/dash', 'get')->middleware());
        self::assertSame(['web'], $this->router->find('/open', 'get')->middleware());
    }

    public function testDefinitionsRoundTripPreservesMiddleware(): void
    {
        $this->router->get('/admin', 'Admin@index', 'admin', ['auth', 'throttle:60,1']);

        $defs = $this->router->definitions();
        self::assertNotNull($defs);

        $fresh = new Route();
        $fresh->loadDefinitions($defs);

        self::assertSame(
            ['auth', 'throttle:60,1'],
            $fresh->find('/admin', 'get')->middleware(),
        );
    }
}

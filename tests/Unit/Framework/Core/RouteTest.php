<?php

namespace Tests\Unit\Framework\Core;

use PHPUnit\Framework\TestCase;
use Silver\Core\Route;

/**
 * Characterizes Route's public contract. Each test instantiates its own
 * Route so the registry state is scoped to the test (no process-global
 * static state to leak between cases).
 */
class RouteTest extends TestCase
{
    private Route $router;

    protected function setUp(): void
    {
        $this->router = new Route();
    }

    public function testRegisterAndFindByMethodAndUrl(): void
    {
        $this->router->get('/b6-test/list', 'Things@index', 'b6.list');

        $route = $this->router->find('/b6-test/list', 'get');

        $this->assertNotNull($route);
        $this->assertSame('get', $route->method());
        $this->assertSame('Things@index', $route->action());
    }

    public function testFindReturnsNullForUnknownUrl(): void
    {
        $this->assertNull($this->router->find('/b6-test/nope-xyz', 'get'));
    }

    public function testWrongMethodDoesNotMatch(): void
    {
        $this->router->get('/b6-test/only-get', 'X@y', 'b6.onlyget');

        $this->assertNull($this->router->find('/b6-test/only-get', 'post'));
    }

    public function testRouteVariableExtraction(): void
    {
        $this->router->get('/b6-test/user/{id}', 'User@show', 'b6.user');

        $route = $this->router->find('/b6-test/user/42', 'get');

        $this->assertNotNull($route);
        $this->assertSame('42', $route->variables()['id']);
    }

    public function testNamedRouteLookupAndUrlGeneration(): void
    {
        $this->router->get('/b6-test/post/{slug}', 'Post@show', 'b6.post');

        $named = $this->router->getRoute('b6.post');

        $this->assertSame('Post@show', $named->action());
        $this->assertStringEndsWith('/b6-test/post/hello', $named->url(['slug' => 'hello']));
    }

    public function testGetRouteThrowsForUnknownName(): void
    {
        $this->expectException(\Exception::class);
        $this->router->getRoute('b6.does-not-exist');
    }
}

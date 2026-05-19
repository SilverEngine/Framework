<?php

namespace Tests\Unit\Framework\Core;

use PHPUnit\Framework\TestCase;
use Silver\Core\Route;

/**
 * Characterizes Route's public contract after the underscore-prefix
 * rename. Uses unique paths per test since route registries are static
 * and process-global.
 */
class RouteTest extends TestCase
{
    public function testRegisterAndFindByMethodAndUrl(): void
    {
        Route::get('/b6-test/list', 'Things@index', 'b6.list');

        $route = Route::find('/b6-test/list', 'get');

        $this->assertNotNull($route);
        $this->assertSame('get', $route->method());
        $this->assertSame('Things@index', $route->action());
    }

    public function testFindReturnsNullForUnknownUrl(): void
    {
        $this->assertNull(Route::find('/b6-test/nope-xyz', 'get'));
    }

    public function testWrongMethodDoesNotMatch(): void
    {
        Route::get('/b6-test/only-get', 'X@y', 'b6.onlyget');

        $this->assertNull(Route::find('/b6-test/only-get', 'post'));
    }

    public function testRouteVariableExtraction(): void
    {
        Route::get('/b6-test/user/{id}', 'User@show', 'b6.user');

        $route = Route::find('/b6-test/user/42', 'get');

        $this->assertNotNull($route);
        $this->assertSame('42', $route->variables()['id']);
    }

    public function testNamedRouteLookupAndUrlGeneration(): void
    {
        Route::get('/b6-test/post/{slug}', 'Post@show', 'b6.post');

        $named = Route::getRoute('b6.post');

        $this->assertSame('Post@show', $named->action());
        $this->assertStringEndsWith('/b6-test/post/hello', $named->url(['slug' => 'hello']));
    }

    public function testGetRouteThrowsForUnknownName(): void
    {
        $this->expectException(\Exception::class);
        Route::getRoute('b6.does-not-exist');
    }
}

<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Core;

use PHPUnit\Framework\TestCase;
use Silver\Core\App;
use Silver\Core\Route;
use Silver\Core\RuntimeContext;

final class RuntimeContextTest extends TestCase
{
    protected function tearDown(): void
    {
        // Restore the detect()-based default for other tests.
        App::instance()->instances()->instance(RuntimeContext::class, RuntimeContext::detect());
    }

    public function testForWebDerivesShapeFromServer(): void
    {
        $ctx = RuntimeContext::forWeb('/srv/app', [
            'SCRIPT_NAME' => '/index.php',
            'SERVER_NAME' => 'example.test',
            'SERVER_PORT' => 80,
            'REQUEST_URI' => '/users/1?tab=posts',
        ]);

        $this->assertSame('/srv/app/', $ctx->root);
        $this->assertSame('', $ctx->basePath);
        $this->assertSame('http://example.test', $ctx->url);
        $this->assertSame('http://example.test/users/1?tab=posts', $ctx->currentUrl);
    }

    public function testForWebSubdirectoryHttpsAndPort(): void
    {
        $ctx = RuntimeContext::forWeb('/srv/app/', [
            'SCRIPT_NAME' => '/sub/index.php',
            'SERVER_NAME' => 'example.test',
            'SERVER_PORT' => 8443,
            'HTTPS'       => 'on',
            'REQUEST_URI' => '/sub/users',
        ]);

        $this->assertSame('/sub', $ctx->basePath);
        $this->assertSame('https://example.test:8443/sub', $ctx->url);
        $this->assertSame('https://example.test:8443/sub/users', $ctx->currentUrl);
    }

    public function testForCliNormalizesRootAndLeavesUrlEmpty(): void
    {
        $ctx = RuntimeContext::forCli('C:\\srv\\app');

        $this->assertSame('C:/srv/app/', $ctx->root);
        $this->assertSame('', $ctx->basePath);
        $this->assertSame('', $ctx->url);
    }

    public function testDetectFallsBackToCwdWithoutConstants(): void
    {
        $ctx = RuntimeContext::detect();

        $this->assertStringEndsWith('/', $ctx->root);
        $this->assertSame('', $ctx->basePath);
    }

    public function testContainerResolvesDetectDefaultAsSingleton(): void
    {
        $this->assertSame(RuntimeContext::current(), RuntimeContext::current());
    }

    public function testRouteUrlUsesSwappedContextBasePath(): void
    {
        App::instance()->instances()->instance(
            RuntimeContext::class,
            new RuntimeContext(root: getcwd() . '/', basePath: '/sub'),
        );

        $route = App::instance()->instances()->make(Route::class);
        $route->reset();
        $route->get('/users/{id}', 'Users@show', 'users.show');

        $this->assertSame('/sub/users/7', $route->getRoute('users.show')->url(['id' => 7]));
        $route->reset();
    }
}

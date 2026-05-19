<?php

namespace Tests\Unit\Framework\Core;

use PHPUnit\Framework\TestCase;
use Silver\Core\Env;
use Silver\Core\Route;
use Silver\Engine\Command;

/**
 * Locks the `php silver optimize` building blocks: the config cache
 * round-trip and route-definition (de)serialisation incl. the
 * Closure-route opt-out, plus the new CLI command tokens.
 */
class OptimizeCacheTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = defined('ROOT') ? ROOT : getcwd() . '/';
        Env::clearConfigCache($this->root);
    }

    protected function tearDown(): void
    {
        // Never leave a real config cache behind for other tests / boot.
        Env::clearConfigCache($this->root);
    }

    public function testConfigCacheRoundTrip(): void
    {
        Env::construct($this->root);
        $appName = Env::name();

        $path = Env::cacheConfig($this->root);
        $this->assertFileExists($path);

        // Re-construct: must now load from cache and expose same data.
        Env::construct($this->root);
        $this->assertSame($appName, Env::name());
        $this->assertNotNull(Env::get('app'));

        $this->assertTrue(Env::clearConfigCache($this->root));
        $this->assertFileDoesNotExist($path);
    }

    public function testRouteDefinitionsRoundTrip(): void
    {
        Route::get('/opt-test/list', 'Things@index', 'opt.list');
        $defs = Route::definitions();

        $this->assertIsArray($defs);
        $found = array_filter($defs, static fn ($d) => $d[1] === '/opt-test/list');
        $this->assertNotEmpty($found);

        Route::loadDefinitions([['get', '/opt-test/replay', 'X@y', 'opt.replay', 'public', 'get']]);
        $this->assertSame('X@y', Route::find('/opt-test/replay', 'get')?->action());
    }

    public function testClosureRouteMakesDefinitionsUncacheable(): void
    {
        Route::get('/opt-test/closure', fn () => 'hi', 'opt.closure');
        $this->assertNull(Route::definitions());
    }

    public function testOptimizeCommandTokens(): void
    {
        $this->assertSame(Command::Optimize, Command::parse('optimize'));
        $this->assertSame(Command::OptimizeClear, Command::parse('optimize:clear'));
        $this->assertNull(Command::parse('optimize:nope'));
    }
}

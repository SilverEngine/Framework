<?php

namespace Tests\Unit\Framework\Core;

use PHPUnit\Framework\TestCase;
use Silver\Core\App;
use Silver\Core\Container;
use Silver\Core\DI;
use Silver\Support\Facade;

class CcDep
{
    public string $tag = 'dep';
}

class CcFacadeTarget
{
    public function ping(string $x): string
    {
        return 'pong:' . $x;
    }
}

class CcFacade extends Facade
{
    protected static function getClass(): string
    {
        return CcFacadeTarget::class;
    }
}

/**
 * Pins the observable container/DI/Facade contract BEFORE the IoC
 * rework. Every assertion here must still hold after Container,
 * constructor injection and Facade rewiring land.
 */
class ContainerContractTest extends TestCase
{
    public function testInstancesRegisterGetAndDuplicateThrow(): void
    {
        $i = new Container();
        $dep = new CcDep();

        $this->assertSame($dep, $i->register($dep));
        $this->assertSame($dep, $i->get(CcDep::class));
        $this->assertNull($i->get('Nope\Missing'));
        $this->assertArrayHasKey(CcDep::class, $i->getAll());

        $this->expectException(\Throwable::class);
        $i->register($dep); // duplicate without force
    }

    public function testInstancesForceOverwriteAndNamed(): void
    {
        $i = new Container();
        $a = new CcDep();
        $b = new CcDep();

        $i->register($a);
        $this->assertSame($b, $i->register($b, true));
        $i->registerNamed('cfg', ['k' => 1]);
        $this->assertSame(['k' => 1], $i->get('cfg'));
    }

    public function testDiCallClosureInjectsBuiltinByNameAndUsesDefault(): void
    {
        $out = app(DI::class)->call(
            fn (string $name, int $n = 7): string => "$name:$n",
            ['name' => 'lex'],
        );
        $this->assertSame('lex:7', $out);
    }

    public function testDiCallInjectsObjectByTypeFqn(): void
    {
        $dep = new CcDep();
        $out = app(DI::class)->call(
            fn (CcDep $d): string => $d->tag,
            [CcDep::class => $dep],
        );
        $this->assertSame('dep', $out);
    }

    public function testDiCallThrowsOnUnresolvableRequiredParam(): void
    {
        $this->expectException(\Throwable::class);
        app(DI::class)->call(fn (string $missing): string => $missing, []);
    }

    public function testDiCallArrayCallableMethodInjection(): void
    {
        $obj = new class {
            public function greet(string $who): string
            {
                return "hi $who";
            }
        };
        $this->assertSame('hi sam', app(DI::class)->call([$obj, 'greet'], ['who' => 'sam']));
    }

    public function testFacadeIsLazySingletonProxy(): void
    {
        $this->assertSame('pong:a', CcFacade::ping('a'));
        $this->assertSame('pong:b', CcFacade::ping('b'));
    }

    public function testAppSingletonAndInstancesRoundTrip(): void
    {
        $app = App::instance();
        $this->assertSame($app, App::instance());

        $dep = new CcDep();
        $app->register($dep);
        $this->assertSame($dep, $app->instances()->get(CcDep::class));
    }
}

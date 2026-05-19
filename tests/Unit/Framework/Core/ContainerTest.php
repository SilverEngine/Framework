<?php

namespace Tests\Unit\Framework\Core;

use PHPUnit\Framework\TestCase;
use Silver\Core\Container;

interface CtClock
{
    public function now(): string;
}

class CtSystemClock implements CtClock
{
    public function now(): string
    {
        return 'fixed';
    }
}

class CtService
{
    public function __construct(public CtClock $clock, public int $n = 3)
    {
    }
}

/**
 * Locks the new IoC behaviour AND that Container is a drop-in for the
 * legacy Instances registry surface (App now holds a Container).
 */
class ContainerTest extends TestCase
{
    public function testLegacyRegistrySurfaceUnchanged(): void
    {
        $c = new Container();
        $o = new CtSystemClock();

        $this->assertSame($o, $c->register($o));
        $this->assertSame($o, $c->get(CtSystemClock::class));
        $this->assertNull($c->get('X\Missing'));
        $this->assertArrayHasKey(CtSystemClock::class, $c->getAll());

        $this->expectException(\Throwable::class);
        $c->register($o); // duplicate-throw preserved
    }

    public function testBindIsTransientSingletonIsCached(): void
    {
        $c = new Container();
        $c->bind(CtClock::class, CtSystemClock::class);
        $this->assertNotSame($c->make(CtClock::class), $c->make(CtClock::class));

        $c->singleton(CtClock::class, CtSystemClock::class);
        $this->assertSame($c->make(CtClock::class), $c->make(CtClock::class));
    }

    public function testClosureFactoryBinding(): void
    {
        $c = new Container();
        $c->singleton(CtClock::class, fn (): CtClock => new CtSystemClock());
        $this->assertInstanceOf(CtSystemClock::class, $c->make(CtClock::class));
    }

    public function testRecursiveConstructorAutowiring(): void
    {
        $c = new Container();
        $c->bind(CtClock::class, CtSystemClock::class);

        $svc = $c->make(CtService::class);
        $this->assertInstanceOf(CtService::class, $svc);
        $this->assertSame('fixed', $svc->clock->now());
        $this->assertSame(3, $svc->n);
    }

    public function testMakeRespectsExplicitParamsAndInstance(): void
    {
        $c = new Container();
        $clock = new CtSystemClock();
        $c->instance(CtClock::class, $clock);

        $svc = $c->make(CtService::class, ['n' => 9]);
        $this->assertSame($clock, $svc->clock);
        $this->assertSame(9, $svc->n);
    }

    public function testMakeUnresolvableThrows(): void
    {
        $c = new Container();
        $this->expectException(\Throwable::class);
        $c->make(CtClock::class); // interface, no binding
    }
}

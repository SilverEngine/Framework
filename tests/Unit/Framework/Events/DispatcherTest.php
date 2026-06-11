<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Events;

use PHPUnit\Framework\TestCase;
use Silver\Core\App;
use Silver\Events\Dispatcher;

final class UserRegisteredStub
{
    public function __construct(public string $name)
    {
    }
}

final class DispatcherTest extends TestCase
{
    public function testListenersRunInOrderAndReturnValuesAreCollected(): void
    {
        $d = new Dispatcher();
        $d->listen('order.placed', fn ($p) => "first:$p");
        $d->listen('order.placed', fn ($p) => "second:$p");

        $this->assertSame(['first:42', 'second:42'], $d->dispatch('order.placed', '42'));
    }

    public function testObjectEventUsesClassNameAndPassesItself(): void
    {
        $d = new Dispatcher();
        $seen = null;
        $d->listen(UserRegisteredStub::class, function (UserRegisteredStub $e) use (&$seen) {
            $seen = $e->name;
        });

        $d->dispatch(new UserRegisteredStub('Lex'));

        $this->assertSame('Lex', $seen);
    }

    public function testDispatchWithoutListenersIsNoOp(): void
    {
        $d = new Dispatcher();
        $this->assertSame([], $d->dispatch('nobody.cares'));
    }

    public function testHasListenersForgetAndFlush(): void
    {
        $d = new Dispatcher();
        $d->listen('a', fn () => null);
        $d->listen('b', fn () => null);

        $this->assertTrue($d->hasListeners('a'));

        $d->forget('a');
        $this->assertFalse($d->hasListeners('a'));
        $this->assertTrue($d->hasListeners('b'));

        $d->flush();
        $this->assertFalse($d->hasListeners('b'));
    }

    public function testContainerResolvesSharedSingleton(): void
    {
        $c = App::instance()->instances();
        $this->assertSame($c->make(Dispatcher::class), $c->make(Dispatcher::class));
    }
}

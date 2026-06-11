<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Http;

use PHPUnit\Framework\TestCase;
use Silver\Core\App;
use Silver\Http\ArraySessionStore;
use Silver\Http\Contracts\SessionStore;
use Silver\Http\NativeSessionStore;
use Silver\Http\Session;

final class SessionStoreTest extends TestCase
{
    protected function tearDown(): void
    {
        // Restore the default backend for other tests.
        App::instance()->instances()->instance(SessionStore::class, new NativeSessionStore());
    }

    public function testSetGetDeleteOnArrayStore(): void
    {
        $s = new ArraySessionStore();

        $this->assertSame('v', $s->set('k', 'v'));
        $this->assertTrue($s->exists('k'));
        $this->assertSame('v', $s->get('k'));

        $s->delete('k');
        $this->assertFalse($s->exists('k'));
        $this->assertSame('fallback', $s->get('k', 'fallback'));
    }

    public function testFlashLivesExactlyOneRotation(): void
    {
        $s = new ArraySessionStore();
        $s->flash('msg', 'saved!');

        // Same request: visible.
        $this->assertSame('saved!', $s->get('msg'));

        // Next request boot: promoted to old-flash, still visible.
        $s->rotateFlash();
        $this->assertSame('saved!', $s->get('msg'));

        // Request after that: gone.
        $s->rotateFlash();
        $this->assertFalse($s->exists('msg'));
    }

    public function testSetOverwritesPreviousFlashOfSameKey(): void
    {
        $s = new ArraySessionStore();
        $s->flash('k', 'flashed');
        $s->set('k', 'persistent');

        $s->rotateFlash();
        $s->rotateFlash();
        $this->assertSame('persistent', $s->get('k'));
    }

    public function testFlushAndKillEmptyTheStore(): void
    {
        $s = new ArraySessionStore();
        $s->set('a', 1);
        $s->flash('b', 2);

        $s->flush();
        $this->assertSame([], $s->all());

        $s->set('c', 3);
        $this->assertTrue($s->kill());
        $this->assertSame([], $s->all());
    }

    public function testSessionFacadeDelegatesToSwappedStore(): void
    {
        $store = new ArraySessionStore();
        App::instance()->instances()->instance(SessionStore::class, $store);

        $_SESSION = [];
        Session::set('facade_key', 'works');

        $this->assertSame('works', $store->get('facade_key'));
        $this->assertSame('works', Session::get('facade_key'));
        $this->assertSame([], $_SESSION, 'swapped backend must not touch $_SESSION');

        Session::construct(); // rotateFlash, must not error on array store
        Session::flush();
        $this->assertFalse(Session::exists('facade_key'));
    }

    public function testNativeStoreReadsAndWritesSession(): void
    {
        App::instance()->instances()->instance(SessionStore::class, new NativeSessionStore());

        $_SESSION = [];
        Session::set('native_key', 'works');

        $this->assertSame('works', $_SESSION['data']['native_key']);
        $this->assertSame('works', Session::get('native_key'));

        Session::flush();
        $this->assertSame([], $_SESSION);
    }
}

<?php

namespace Tests\Unit\Framework\Core;

use PHPUnit\Framework\TestCase;
use Silver\Core\App;
use Silver\Core\Container;
use Silver\Core\Hook;
use Silver\FileSystem\FileSystem;
use Silver\FileSystem\LocalFileSystem;

class HookTest extends TestCase
{
    private function hook(): Hook
    {
        return app(Hook::class);
    }

    protected function setUp(): void
    {
        $this->hook()->reset();
    }

    public function testBeforeCanMutateArgs(): void
    {
        $c = new Container();
        $c->singleton(FileSystem::class, LocalFileSystem::class);
        $c->before(FileSystem::class, 'write', function ($_, array $args) {
            // Force every write into /tmp regardless of caller's path.
            [$path, $contents] = $args;
            return ['/tmp/hooked-' . basename($path), $contents];
        });

        $fs = $c->make(FileSystem::class);
        $tmp = '/tmp/hooked-hook-target.txt';
        @unlink($tmp);

        $fs->write('/anywhere/hook-target.txt', 'payload');

        $this->assertFileExists($tmp);
        $this->assertSame('payload', file_get_contents($tmp));
        @unlink($tmp);
    }

    public function testAfterCanTransformResult(): void
    {
        $c = new Container();
        $c->singleton(FileSystem::class, LocalFileSystem::class);
        $tmp = tempnam(sys_get_temp_dir(), 'hook');
        file_put_contents($tmp, 'lower');

        $c->after(FileSystem::class, 'read', fn ($_, $__, string $result) => strtoupper($result));

        $fs = $c->make(FileSystem::class);
        $this->assertSame('LOWER', $fs->read($tmp));
        @unlink($tmp);
    }

    public function testAfterDeferredOnlyRunsWhenFlushed(): void
    {
        $c = new Container();
        $c->singleton(FileSystem::class, LocalFileSystem::class);
        $tmp = tempnam(sys_get_temp_dir(), 'hook');
        file_put_contents($tmp, 'x');

        $seen = [];
        $c->afterDeferred(FileSystem::class, 'read', function ($_, $args, $result) use (&$seen) {
            $seen[] = $result;
        });

        $fs = $c->make(FileSystem::class);
        $fs->read($tmp);

        $this->assertSame([], $seen, 'deferred must not fire inline');

        $this->hook()->runDeferred();
        $this->assertSame(['x'], $seen);
        @unlink($tmp);
    }

    public function testDecoratorWrapsResolution(): void
    {
        $c = new Container();
        $c->singleton(FileSystem::class, LocalFileSystem::class);

        // Wrap FileSystem with a no-op subclass to prove the decorator runs.
        $c->extend(FileSystem::class, fn ($inner) => new class ($inner) implements FileSystem {
            public function __construct(private FileSystem $inner) {}
            public function exists(string $p): bool { return $this->inner->exists($p); }
            public function isFile(string $p): bool { return $this->inner->isFile($p); }
            public function isDir(string $p): bool { return $this->inner->isDir($p); }
            public function read(string $p): string { return 'decorated:' . $this->inner->read($p); }
            public function write(string $p, string $c, bool $l = false): int { return $this->inner->write($p, $c, $l); }
            public function append(string $p, string $c): int { return $this->inner->append($p, $c); }
            public function delete(string $p): bool { return $this->inner->delete($p); }
            public function move(string $f, string $t): bool { return $this->inner->move($f, $t); }
            public function mkdir(string $p, int $m = 0o775, bool $r = true): bool { return $this->inner->mkdir($p, $m, $r); }
            public function listing(string $d, string $pat = '*'): array { return $this->inner->listing($d, $pat); }
            public function size(string $p): int { return $this->inner->size($p); }
            public function mtime(string $p): int { return $this->inner->mtime($p); }
        });

        $tmp = tempnam(sys_get_temp_dir(), 'hook');
        file_put_contents($tmp, 'inner-value');

        $fs = $c->make(FileSystem::class);
        $this->assertSame('decorated:inner-value', $fs->read($tmp));
        @unlink($tmp);
    }
}

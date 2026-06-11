<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\FileSystem;

use PHPUnit\Framework\TestCase;
use Silver\FileSystem\LocalFileSystem;

final class LocalFileSystemTest extends TestCase
{
    private string $dir;
    private LocalFileSystem $fs;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/silver_fs_' . getmypid() . '_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->fs = new LocalFileSystem();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/{*,.*}', GLOB_BRACE) ?: [] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        foreach (glob($this->dir . '/*', GLOB_ONLYDIR) ?: [] as $d) {
            @rmdir($d);
        }
        @rmdir($this->dir);
    }

    private function path(string $name): string
    {
        return $this->dir . '/' . $name;
    }

    public function testWriteReadRoundTrip(): void
    {
        $written = $this->fs->write($this->path('a.txt'), 'hello');

        self::assertSame(5, $written);
        self::assertSame('hello', $this->fs->read($this->path('a.txt')));
    }

    public function testAppendAddsToExistingContent(): void
    {
        $this->fs->write($this->path('a.txt'), 'one');
        $this->fs->append($this->path('a.txt'), ' two');

        self::assertSame('one two', $this->fs->read($this->path('a.txt')));
    }

    public function testExistsIsFileIsDir(): void
    {
        $this->fs->write($this->path('a.txt'), 'x');

        self::assertTrue($this->fs->exists($this->path('a.txt')));
        self::assertTrue($this->fs->isFile($this->path('a.txt')));
        self::assertFalse($this->fs->isDir($this->path('a.txt')));
        self::assertTrue($this->fs->isDir($this->dir));
        self::assertFalse($this->fs->exists($this->path('missing.txt')));
    }

    public function testDeleteRemovesFile(): void
    {
        $this->fs->write($this->path('a.txt'), 'x');

        self::assertTrue($this->fs->delete($this->path('a.txt')));
        self::assertFalse($this->fs->exists($this->path('a.txt')));
    }

    public function testMoveRenamesFile(): void
    {
        $this->fs->write($this->path('from.txt'), 'payload');

        self::assertTrue($this->fs->move($this->path('from.txt'), $this->path('to.txt')));
        self::assertFalse($this->fs->exists($this->path('from.txt')));
        self::assertSame('payload', $this->fs->read($this->path('to.txt')));
    }

    public function testMkdirCreatesNestedDirectories(): void
    {
        self::assertTrue($this->fs->mkdir($this->path('a/b/c')));
        self::assertTrue($this->fs->isDir($this->path('a/b/c')));

        // cleanup nested dirs (tearDown only handles one level)
        rmdir($this->path('a/b/c'));
        rmdir($this->path('a/b'));
    }

    public function testListingReturnsMatchingFilesOnly(): void
    {
        $this->fs->write($this->path('one.txt'), '1');
        $this->fs->write($this->path('two.txt'), '2');
        $this->fs->write($this->path('three.log'), '3');
        $this->fs->mkdir($this->path('sub'));

        $txt = $this->fs->listing($this->dir, '*.txt');

        self::assertCount(2, $txt);
        foreach ($txt as $f) {
            self::assertStringEndsWith('.txt', $f);
        }
        // Directories never appear, even with the default pattern.
        foreach ($this->fs->listing($this->dir) as $f) {
            self::assertTrue(is_file($f));
        }
    }

    public function testSizeAndMtime(): void
    {
        $this->fs->write($this->path('a.txt'), 'four');

        self::assertSame(4, $this->fs->size($this->path('a.txt')));
        self::assertEqualsWithDelta(time(), $this->fs->mtime($this->path('a.txt')), 5);
    }

    public function testHooksInterceptWrites(): void
    {
        $hook = app(\Silver\Core\Hook::class);
        $hook->reset();
        $seen = [];
        $hook->before(LocalFileSystem::class, 'write', function (object $fs, array $args) use (&$seen): void {
            $seen[] = $args[0];
        });

        $this->fs->write($this->path('hooked.txt'), 'x');

        self::assertSame([$this->path('hooked.txt')], $seen);
        $hook->reset();
    }
}

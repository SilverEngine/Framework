<?php

namespace Tests\Unit\Framework\Support;

use PHPUnit\Framework\TestCase;
use Silver\Support\RequestRecorder;

/**
 * Locks recorder()->find() input hardening — the id comes from
 * a query string, so it must reject path traversal / non-id input
 * before touching the filesystem.
 */
class RequestRecorderTest extends TestCase
{
    public function testFindRejectsPathTraversalAndJunk(): void
    {
        $this->assertNull(recorder()->find('../../etc/passwd'));
        $this->assertNull(recorder()->find('..%2F..%2Fsecret'));
        $this->assertNull(recorder()->find('foo/bar'));
        $this->assertNull(recorder()->find('a b'));
        $this->assertNull(recorder()->find(''));
    }

    public function testFindReturnsNullForWellFormedButMissingId(): void
    {
        $this->assertNull(recorder()->find('1700000000000-deadbe'));
    }

    public function testDirIsUnderStorageDebug(): void
    {
        $this->assertStringContainsString('storage/debug/recordings/', recorder()->dir());
    }
}

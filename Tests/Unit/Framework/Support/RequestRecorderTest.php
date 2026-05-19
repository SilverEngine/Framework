<?php

namespace Tests\Unit\Framework\Support;

use PHPUnit\Framework\TestCase;
use Silver\Support\RequestRecorder;

/**
 * Locks RequestRecorder::find() input hardening — the id comes from
 * a query string, so it must reject path traversal / non-id input
 * before touching the filesystem.
 */
class RequestRecorderTest extends TestCase
{
    public function testFindRejectsPathTraversalAndJunk(): void
    {
        $this->assertNull(RequestRecorder::find('../../etc/passwd'));
        $this->assertNull(RequestRecorder::find('..%2F..%2Fsecret'));
        $this->assertNull(RequestRecorder::find('foo/bar'));
        $this->assertNull(RequestRecorder::find('a b'));
        $this->assertNull(RequestRecorder::find(''));
    }

    public function testFindReturnsNullForWellFormedButMissingId(): void
    {
        $this->assertNull(RequestRecorder::find('1700000000000-deadbe'));
    }

    public function testDirIsUnderStorageDebug(): void
    {
        $this->assertStringContainsString('Storage/debug/recordings/', RequestRecorder::dir());
    }
}

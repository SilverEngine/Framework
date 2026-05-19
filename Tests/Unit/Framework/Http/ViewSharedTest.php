<?php

namespace Tests\Unit\Framework\Http;

use PHPUnit\Framework\TestCase;
use Silver\Http\View;

/**
 * Locks View::sharedFor() behaviour after the array_any refactor:
 * shared data always present, composers merge once on wildcard match,
 * non-matching names contribute nothing.
 */
class ViewSharedTest extends TestCase
{
    protected function setUp(): void
    {
        View::flushShared();
    }

    protected function tearDown(): void
    {
        View::flushShared();
    }

    public function testSharedAlwaysPresent(): void
    {
        View::share('app', 'SilverEngine');

        $this->assertSame('SilverEngine', View::sharedFor('Anything')['app']);
    }

    public function testComposerWildcardMatchMergesOnce(): void
    {
        View::composer('Users/*', static fn (string $name): array => ['ctx' => $name]);

        $data = View::sharedFor('Users/Edit');

        $this->assertSame('Users/Edit', $data['ctx']);
    }

    public function testComposerNonMatchContributesNothing(): void
    {
        View::composer('Users/*', static fn (string $name): array => ['ctx' => $name]);

        $this->assertArrayNotHasKey('ctx', View::sharedFor('Posts/Show'));
    }
}

<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Support;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Cache\ArrayStore;
use Silver\Support\RateLimiter;

/**
 * F3 — general sliding-window rate limiter over a CacheStore.
 * The clock is injected so window expiry is tested without sleep().
 */
final class RateLimiterTest extends TestCase
{
    private float $now = 1_000_000.0;

    private function limiter(): RateLimiter
    {
        return new RateLimiter(new ArrayStore(), fn (): float => $this->now);
    }

    public function testHitIncrementsAttempts(): void
    {
        $rl = $this->limiter();

        self::assertSame(1, $rl->hit('k', 60));
        self::assertSame(2, $rl->hit('k', 60));
        self::assertSame(2, $rl->attempts('k', 60));
    }

    public function testAttemptAllowsUnderLimitAndBlocksAtLimit(): void
    {
        $rl = $this->limiter();

        self::assertTrue($rl->attempt('k', 2, 60));
        self::assertTrue($rl->attempt('k', 2, 60));
        self::assertFalse($rl->attempt('k', 2, 60));
    }

    public function testBlockedAttemptDoesNotConsumeASlot(): void
    {
        $rl = $this->limiter();

        $rl->attempt('k', 1, 60);
        $rl->attempt('k', 1, 60); // blocked
        self::assertSame(1, $rl->attempts('k', 60));
    }

    public function testWindowSlidesHitsExpireIndividually(): void
    {
        $rl = $this->limiter();

        $rl->hit('k', 60);          // t = 0
        $this->now += 30;
        $rl->hit('k', 60);          // t = 30
        self::assertSame(2, $rl->attempts('k', 60));

        $this->now += 31;           // t = 61 — first hit expired, second alive
        self::assertSame(1, $rl->attempts('k', 60));
        self::assertTrue($rl->attempt('k', 2, 60));

        $this->now += 60;           // everything expired
        self::assertSame(0, $rl->attempts('k', 60));
    }

    public function testRemaining(): void
    {
        $rl = $this->limiter();

        self::assertSame(3, $rl->remaining('k', 3, 60));
        $rl->hit('k', 60);
        self::assertSame(2, $rl->remaining('k', 3, 60));
    }

    public function testAvailableInReportsSecondsUntilOldestHitExpires(): void
    {
        $rl = $this->limiter();

        $rl->hit('k', 60);
        $this->now += 45;
        self::assertSame(15, $rl->availableIn('k', 60));
    }

    public function testAvailableInIsZeroWithNoHits(): void
    {
        self::assertSame(0, $this->limiter()->availableIn('k', 60));
    }

    public function testClearResetsKey(): void
    {
        $rl = $this->limiter();

        $rl->hit('k', 60);
        $rl->clear('k');
        self::assertSame(0, $rl->attempts('k', 60));
    }

    public function testKeysAreIndependent(): void
    {
        $rl = $this->limiter();

        $rl->hit('a', 60);
        self::assertSame(0, $rl->attempts('b', 60));
    }
}

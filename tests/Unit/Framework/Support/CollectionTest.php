<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Support;

use PHPUnit\Framework\TestCase;
use Silver\Support\Collection;

/**
 * Silver\Support\Collection — the general-purpose collection (promoted
 * from Silver\Orm\Collection, which stays as a BC subclass).
 */
final class CollectionTest extends TestCase
{
    public function testOrmCollectionIsASupportCollection(): void
    {
        $orm = new \Silver\Orm\Collection([1, 2]);

        self::assertInstanceOf(Collection::class, $orm);
        // Chains keep the subclass (new static everywhere).
        self::assertInstanceOf(\Silver\Orm\Collection::class, $orm->map(fn (int $v): int => $v * 2));
    }

    public function testMakeFromIterable(): void
    {
        $gen = (static function (): \Generator {
            yield 'a';
            yield 'b';
        })();

        self::assertSame(['a', 'b'], Collection::make($gen)->all());
    }

    public function testMapFilterValuesChain(): void
    {
        $result = new Collection([1, 2, 3, 4])
            ->map(fn (int $v): int => $v * 10)
            ->filter(fn (int $v): bool => $v > 15)
            ->values();

        self::assertSame([20, 30, 40], $result->all());
    }

    public function testPluckWithDottedPathAndIndexBy(): void
    {
        $rows = new Collection([
            ['id' => 1, 'team' => ['name' => 'red']],
            ['id' => 2, 'team' => ['name' => 'blue']],
        ]);

        self::assertSame(['red', 'blue'], $rows->pluck('team.name')->values()->all());
        self::assertSame(['1' => 'red', '2' => 'blue'], $rows->pluck('team.name', 'id')->all());
    }

    public function testGroupByAndPartition(): void
    {
        $nums = new Collection([1, 2, 3, 4, 5]);

        $groups = $nums->groupBy(fn (int $v): string => $v % 2 === 0 ? 'even' : 'odd');
        self::assertSame([1, 3, 5], $groups['odd']->values()->all());

        [$even, $odd] = $nums->partition(fn (int $v): bool => $v % 2 === 0);
        self::assertSame([2, 4], $even->values()->all());
        self::assertSame([1, 3, 5], $odd->values()->all());
    }

    public function testSumFirstLastCount(): void
    {
        $c = new Collection([['n' => 1], ['n' => 2], ['n' => 3]]);

        self::assertSame(6.0, $c->sum('n'));
        self::assertSame(['n' => 1], $c->first());
        self::assertSame(['n' => 3], $c->last());
        self::assertCount(3, $c);
    }

    public function testCollectHelper(): void
    {
        $c = collect([1, 2, 3]);

        self::assertInstanceOf(Collection::class, $c);
        self::assertSame([1, 2, 3], $c->all());
    }
}

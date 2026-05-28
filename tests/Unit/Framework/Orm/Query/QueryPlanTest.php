<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Query;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Connection\Driver;
use Silver\Orm\Query\Builder;
use Silver\Orm\Query\Compiler;
use Silver\Orm\Query\QueryPlan;

final class QueryPlanTest extends TestCase
{
    private ConnectionManager $cm;

    protected function setUp(): void
    {
        $this->cm = new ConnectionManager();
        $this->cm->connect('default', 'sqlite::memory:');
        $this->cm->setDefault('default');
        $this->cm->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, team_id INTEGER)');
        $this->cm->exec('CREATE INDEX idx_users_team ON users(team_id)');
    }

    private function q(): Builder
    {
        return new Builder($this->cm, new Compiler($this->cm));
    }

    public function testExplainReturnsPlanWithoutTiming(): void
    {
        $plan = $this->q()->from('users')->where('team_id', 1)->explain();

        self::assertInstanceOf(QueryPlan::class, $plan);
        self::assertSame(Driver::Sqlite, $plan->driver);
        self::assertNull($plan->totalMs, 'explain() must not carry timing');
        self::assertStringStartsWith('EXPLAIN QUERY PLAN ', $plan->sql);
        self::assertNotEmpty($plan->rows);
        self::assertStringContainsString('Query:',   $plan->formatted);
        self::assertStringNotContainsString('Total:', $plan->formatted);
    }

    public function testAnalyzeAttachesTimingAndPreservesBindings(): void
    {
        $plan = $this->q()->from('users')->where('team_id', 99)->analyze();

        self::assertNotNull($plan->totalMs);
        self::assertGreaterThanOrEqual(0.0, $plan->totalMs);
        self::assertSame([99], $plan->bindings);
        self::assertStringContainsString('Total:', $plan->formatted);
    }

    public function testExplainCountReshapesAggregateSelect(): void
    {
        $plan = $this->q()->from('users')->where('team_id', 1)->explainCount();
        self::assertStringContainsString('COUNT(*)', $plan->originalSql);
    }

    public function testFormattedOutputCarriesQueryText(): void
    {
        $plan = $this->q()->from('users')->where('team_id', 1)->explain();
        self::assertStringContainsString('SELECT * FROM "users" WHERE "team_id" = ?', $plan->formatted);
    }
}

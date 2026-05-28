<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Query\Grammar;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Query\Builder;
use Silver\Orm\Query\Compiler;

/**
 * Pure string assertions — no DB touch. Locks the SQL shape the
 * grammar emits for each Builder method.
 */
final class SqliteGrammarTest extends TestCase
{
    private function builder(): Builder
    {
        $cm = new ConnectionManager();
        $cm->connect('default', 'sqlite::memory:');
        $cm->setDefault('default');
        return new Builder($cm, new Compiler($cm));
    }

    public function testSelectAllStarWhenNoColumnsGiven(): void
    {
        $b = $this->builder()->from('users');
        self::assertSame('SELECT * FROM "users"', $b->toSql());
        self::assertSame([], $b->getBindings());
    }

    public function testSelectQualifiedColumnsKeepDottedQuoting(): void
    {
        $b = $this->builder()->from('users')->select(['id', 'users.email']);
        self::assertSame('SELECT "id", "users"."email" FROM "users"', $b->toSql());
    }

    public function testWhereEqShorthandBindsValue(): void
    {
        $b = $this->builder()->from('users')->where('email', 'a@b');
        self::assertSame('SELECT * FROM "users" WHERE "email" = ?', $b->toSql());
        self::assertSame(['a@b'], $b->getBindings());
    }

    public function testWhereExplicitOp(): void
    {
        $b = $this->builder()->from('users')->where('age', '>=', 18);
        self::assertSame('SELECT * FROM "users" WHERE "age" >= ?', $b->toSql());
        self::assertSame([18], $b->getBindings());
    }

    public function testWhereInProducesPlaceholderList(): void
    {
        $b = $this->builder()->from('users')->whereIn('id', [1, 2, 3]);
        self::assertSame('SELECT * FROM "users" WHERE "id" IN (?, ?, ?)', $b->toSql());
        self::assertSame([1, 2, 3], $b->getBindings());
    }

    public function testWhereInWithEmptyListBecomesFalse(): void
    {
        $b = $this->builder()->from('users')->whereIn('id', []);
        self::assertSame('SELECT * FROM "users" WHERE 1 = 0', $b->toSql());
        self::assertSame([], $b->getBindings());
    }

    public function testWhereNullAndNotNull(): void
    {
        $b = $this->builder()->from('users')->whereNull('deleted_at')->whereNotNull('email');
        self::assertSame(
            'SELECT * FROM "users" WHERE "deleted_at" IS NULL AND "email" IS NOT NULL',
            $b->toSql(),
        );
    }

    public function testNestedClosureGroupsAndOrComposes(): void
    {
        $b = $this->builder()->from('users')
            ->where('status', 'active')
            ->where(fn (Builder $q) => $q->where('role', 'admin')->orWhere('role', 'owner'));

        self::assertSame(
            'SELECT * FROM "users" WHERE "status" = ? AND ("role" = ? OR "role" = ?)',
            $b->toSql(),
        );
        self::assertSame(['active', 'admin', 'owner'], $b->getBindings());
    }

    public function testJoinAndLeftJoinEmitTheRightKindword(): void
    {
        $b = $this->builder()->from('users')
            ->join('teams', 'teams.id', '=', 'users.team_id')
            ->leftJoin('audits', 'audits.user_id', '=', 'users.id');

        self::assertSame(
            'SELECT * FROM "users" '
            . 'INNER JOIN "teams" ON "teams"."id" = "users"."team_id" '
            . 'LEFT JOIN "audits" ON "audits"."user_id" = "users"."id"',
            $b->toSql(),
        );
    }

    public function testGroupByHavingOrderByLimitOffset(): void
    {
        $b = $this->builder()->from('users')
            ->select(['team_id'])
            ->groupBy('team_id')
            ->having('team_id', '>', 0)
            ->orderBy('team_id', 'desc')
            ->limit(10)->offset(20);

        self::assertSame(
            'SELECT "team_id" FROM "users" GROUP BY "team_id" '
            . 'HAVING "team_id" > ? ORDER BY "team_id" DESC LIMIT 10 OFFSET 20',
            $b->toSql(),
        );
        self::assertSame([0], $b->getBindings());
    }

    public function testWhereExistsRecursesSubquery(): void
    {
        $b = $this->builder()->from('users')
            ->whereExists(fn (Builder $q) => $q
                ->from('subscriptions')
                ->whereColumn('subscriptions.user_id', 'users.id')
                ->where('active', true));

        self::assertSame(
            'SELECT * FROM "users" WHERE EXISTS '
            . '(SELECT * FROM "subscriptions" '
            . 'WHERE "subscriptions"."user_id" = "users"."id" AND "active" = ?)',
            $b->toSql(),
        );
        self::assertSame([true], $b->getBindings());
    }

    public function testBetweenEmitsThreeBindingsInOrder(): void
    {
        $b = $this->builder()->from('orders')->whereBetween('total', 100, 500);
        self::assertSame(
            'SELECT * FROM "orders" WHERE "total" BETWEEN ? AND ?',
            $b->toSql(),
        );
        self::assertSame([100, 500], $b->getBindings());
    }

    public function testDistinct(): void
    {
        $b = $this->builder()->from('users')->select(['email'])->distinct();
        self::assertSame('SELECT DISTINCT "email" FROM "users"', $b->toSql());
    }
}

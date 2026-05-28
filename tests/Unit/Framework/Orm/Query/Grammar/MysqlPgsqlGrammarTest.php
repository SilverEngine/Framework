<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Query\Grammar;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Connection\Driver;
use Silver\Orm\Query\Grammar\MysqlGrammar;
use Silver\Orm\Query\Grammar\PgsqlGrammar;
use Silver\Orm\Query\Node\Binding;
use Silver\Orm\Query\Node\Expression;
use Silver\Orm\Query\Node\Identifier;
use Silver\Orm\Query\QueryState;

/**
 * Query grammar parity — feed identical state into mysql + pgsql
 * grammars and verify each emits driver-appropriate quoting + the
 * right EXPLAIN prefixes.
 */
final class MysqlPgsqlGrammarTest extends TestCase
{
    private function selectAllFromUsersWhereId(int $id): QueryState
    {
        $s = new QueryState();
        $s->from   = new Identifier('users');
        $s->wheres = [new Expression('=', [new Identifier('id'), new Binding($id)])];
        return $s;
    }

    public function testMysqlBackticksAndWhere(): void
    {
        $g = new MysqlGrammar();
        [$sql, $bindings] = $g->compileSelect($this->selectAllFromUsersWhereId(5));

        self::assertSame('SELECT * FROM `users` WHERE `id` = ?', $sql);
        self::assertSame([5], $bindings);
    }

    public function testPgsqlDoubleQuotesAndWhere(): void
    {
        $g = new PgsqlGrammar();
        [$sql, $bindings] = $g->compileSelect($this->selectAllFromUsersWhereId(5));

        self::assertSame('SELECT * FROM "users" WHERE "id" = ?', $sql);
        self::assertSame([5], $bindings);
    }

    public function testExplainPrefixesAreDriverAppropriate(): void
    {
        self::assertSame('EXPLAIN FORMAT=TREE ',          (new MysqlGrammar())->explainPrefix());
        self::assertSame('EXPLAIN ANALYZE FORMAT=TREE ',  (new MysqlGrammar())->analyzePrefix());
        self::assertSame('EXPLAIN (FORMAT JSON) ',        (new PgsqlGrammar())->explainPrefix());
        self::assertSame('EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) ', (new PgsqlGrammar())->analyzePrefix());
    }

    public function testDriverEnumIsReportedCorrectly(): void
    {
        self::assertSame(Driver::Mysql, (new MysqlGrammar())->driver());
        self::assertSame(Driver::Pgsql, (new PgsqlGrammar())->driver());
    }

    public function testInsertUsesDriverQuoting(): void
    {
        $mysql = (new MysqlGrammar())->compileInsert('users', [['email' => 'a@b']]);
        self::assertSame('INSERT INTO `users` (`email`) VALUES (?)', $mysql[0]);
        self::assertSame(['a@b'], $mysql[1]);

        $pgsql = (new PgsqlGrammar())->compileInsert('users', [['email' => 'a@b']]);
        self::assertSame('INSERT INTO "users" ("email") VALUES (?)', $pgsql[0]);
    }
}

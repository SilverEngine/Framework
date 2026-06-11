<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Query;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Query\Builder;
use Silver\Orm\Query\Compiler;
use Silver\Orm\Query\Grammar\MysqlGrammar;
use Silver\Orm\Query\Grammar\PgsqlGrammar;
use Silver\Orm\Query\Node\Binding;
use Silver\Orm\Query\Node\Expression;
use Silver\Orm\Query\Node\Identifier;
use Silver\Orm\Query\QueryState;

final class WhereJsonContainsTest extends TestCase
{
    // ---------- grammar level ----------

    private function jsonContainsState(mixed $value): QueryState
    {
        $s = new QueryState();
        $s->from   = new Identifier('users');
        $s->wheres = [new Expression('JSON CONTAINS', [new Identifier('tags'), new Binding($value)])];
        return $s;
    }

    public function testMysqlCompilesJsonContains(): void
    {
        [$sql, $bindings] = new MysqlGrammar()->compileSelect($this->jsonContainsState('php'));

        self::assertSame('SELECT * FROM `users` WHERE JSON_CONTAINS(`tags`, ?)', $sql);
        self::assertSame(['"php"'], $bindings);
    }

    public function testPgsqlCompilesJsonbContainment(): void
    {
        [$sql, $bindings] = new PgsqlGrammar()->compileSelect($this->jsonContainsState('php'));

        self::assertSame('SELECT * FROM "users" WHERE "tags"::jsonb @> ?', $sql);
        self::assertSame(['"php"'], $bindings);
    }

    public function testMysqlEncodesArrayValueAsJson(): void
    {
        [, $bindings] = new MysqlGrammar()->compileSelect($this->jsonContainsState(['a', 'b']));

        self::assertSame(['["a","b"]'], $bindings);
    }

    // ---------- behaviour on sqlite ----------

    private ConnectionManager $cm;

    private function q(): Builder
    {
        $this->cm = new ConnectionManager();
        $this->cm->connect('default', 'sqlite::memory:');
        $this->cm->setDefault('default');
        $this->cm->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, tags TEXT)');
        $b = new Builder($this->cm, new Compiler($this->cm));
        $b->from('users')->insert([
            ['name' => 'Alice', 'tags' => '["php","sql"]'],
            ['name' => 'Bob',   'tags' => '["js"]'],
            ['name' => 'Cara',  'tags' => '["php","js"]'],
        ]);
        return new Builder($this->cm, new Compiler($this->cm))->from('users');
    }

    public function testSqliteFiltersByScalarContainment(): void
    {
        $names = $this->q()->whereJsonContains('tags', 'php')->orderBy('id')->pluck('name');

        self::assertSame(['Alice', 'Cara'], $names);
    }

    public function testSqliteArrayValueMeansContainsAll(): void
    {
        $names = $this->q()->whereJsonContains('tags', ['php', 'js'])->pluck('name');

        self::assertSame(['Cara'], $names);
    }

    public function testSqliteNoMatchesReturnsEmpty(): void
    {
        $names = $this->q()->whereJsonContains('tags', 'rust')->pluck('name');

        self::assertSame([], $names);
    }
}

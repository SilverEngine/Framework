<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Query\Grammar;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Query\Grammar\MysqlGrammar;
use Silver\Orm\Query\Grammar\PgsqlGrammar;
use Silver\Orm\Query\Grammar\SqliteGrammar;
use Silver\Orm\Query\Node\Identifier;
use Silver\Orm\Query\QueryState;

final class UpsertLockUnionGrammarTest extends TestCase
{
    private function fromUsers(): QueryState
    {
        $s = new QueryState();
        $s->from = new Identifier('users');
        return $s;
    }

    // ---- upsert -------------------------------------------------------

    public function testSqliteAndPgsqlUpsertUseOnConflictExcluded(): void
    {
        $rows = [['email' => 'a@b', 'name' => 'Alice']];

        [$sql, $bindings] = (new SqliteGrammar())->compileUpsert('users', $rows, ['email'], ['name']);
        self::assertSame(
            'INSERT INTO "users" ("email", "name") VALUES (?, ?)'
            . ' ON CONFLICT ("email") DO UPDATE SET "name" = excluded."name"',
            $sql,
        );
        self::assertSame(['a@b', 'Alice'], $bindings);

        [$sql] = (new PgsqlGrammar())->compileUpsert('users', $rows, ['email'], ['name']);
        self::assertStringContainsString('ON CONFLICT ("email") DO UPDATE SET "name" = excluded."name"', $sql);
    }

    public function testMysqlUpsertUsesOnDuplicateKey(): void
    {
        [$sql, $bindings] = (new MysqlGrammar())
            ->compileUpsert('users', [['email' => 'a@b', 'name' => 'Alice']], ['email'], ['name']);

        self::assertSame(
            'INSERT INTO `users` (`email`, `name`) VALUES (?, ?)'
            . ' ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)',
            $sql,
        );
        self::assertSame(['a@b', 'Alice'], $bindings);
    }

    public function testUpsertRequiresUniqueByAndUpdateColumns(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SqliteGrammar())->compileUpsert('users', [['email' => 'a@b']], [], []);
    }

    // ---- insertOrIgnore ----------------------------------------------

    public function testInsertOrIgnorePerDriver(): void
    {
        $rows = [['email' => 'a@b']];

        [$sqlite] = (new SqliteGrammar())->compileInsertOrIgnore('users', $rows);
        self::assertSame('INSERT INTO "users" ("email") VALUES (?) ON CONFLICT DO NOTHING', $sqlite);

        [$pgsql] = (new PgsqlGrammar())->compileInsertOrIgnore('users', $rows);
        self::assertSame('INSERT INTO "users" ("email") VALUES (?) ON CONFLICT DO NOTHING', $pgsql);

        [$mysql] = (new MysqlGrammar())->compileInsertOrIgnore('users', $rows);
        self::assertSame('INSERT IGNORE INTO `users` (`email`) VALUES (?)', $mysql);
    }

    // ---- locks ---------------------------------------------------------

    public function testLockClausesPerDriver(): void
    {
        $update = $this->fromUsers();
        $update->lock = 'update';

        $share = $this->fromUsers();
        $share->lock = 'share';

        [$sql] = (new PgsqlGrammar())->compileSelect($update);
        self::assertSame('SELECT * FROM "users" FOR UPDATE', $sql);
        [$sql] = (new PgsqlGrammar())->compileSelect($share);
        self::assertSame('SELECT * FROM "users" FOR SHARE', $sql);

        [$sql] = (new MysqlGrammar())->compileSelect($update);
        self::assertSame('SELECT * FROM `users` FOR UPDATE', $sql);
        [$sql] = (new MysqlGrammar())->compileSelect($share);
        self::assertSame('SELECT * FROM `users` LOCK IN SHARE MODE', $sql);

        // Sqlite: whole-db locking, clause suppressed entirely.
        [$sql] = (new SqliteGrammar())->compileSelect($update);
        self::assertSame('SELECT * FROM "users"', $sql);
    }

    public function testRawLockStringPassesThrough(): void
    {
        $s = $this->fromUsers();
        $s->lock = 'FOR NO KEY UPDATE';

        [$sql] = (new PgsqlGrammar())->compileSelect($s);
        self::assertSame('SELECT * FROM "users" FOR NO KEY UPDATE', $sql);
    }

    // ---- unions ---------------------------------------------------------

    public function testUnionAndUnionAllCompile(): void
    {
        $other = new QueryState();
        $other->from = new Identifier('admins');

        $s = $this->fromUsers();
        $s->unions = [[$other, false]];
        [$sql] = (new SqliteGrammar())->compileSelect($s);
        self::assertSame('SELECT * FROM "users" UNION SELECT * FROM "admins"', $sql);

        $s = $this->fromUsers();
        $s->unions = [[$other, true]];
        [$sql] = (new SqliteGrammar())->compileSelect($s);
        self::assertSame('SELECT * FROM "users" UNION ALL SELECT * FROM "admins"', $sql);
    }
}

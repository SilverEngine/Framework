<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Schema;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Schema\Blueprint;
use Silver\Orm\Schema\Schema;

/**
 * Sqlite has no ALTER COLUMN — change() must transparently rebuild the
 * table (create temp, copy data, drop, rename, restore indexes).
 */
final class SqliteChangeColumnTest extends TestCase
{
    private ConnectionManager $cm;

    protected function setUp(): void
    {
        $this->cm = new ConnectionManager();
        $this->cm->connect('default', 'sqlite::memory:');
        $this->cm->setDefault('default');
        Schema::bind($this->cm);
        Schema::useConnection(null);

        Schema::create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('email')->unique();
            $t->string('name');
            $t->int('age');
            $t->index('name');
        });

        $this->cm->raw('INSERT INTO users (email, name, age) VALUES (?, ?, ?)', ['a@x.io', 'Alice', 30]);
        $this->cm->raw('INSERT INTO users (email, name, age) VALUES (?, ?, ?)', ['b@x.io', 'Bob', 40]);
    }

    public function testChangeMakesColumnNullableAndKeepsData(): void
    {
        Schema::table('users', function (Blueprint $t): void {
            $t->string('name')->nullable()->change();
        });

        // Data survived the rebuild.
        $rows = $this->cm->raw('SELECT * FROM users ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
        self::assertCount(2, $rows);
        self::assertSame('Alice', $rows[0]['name']);
        self::assertSame(40, (int) $rows[1]['age']);

        // NULL is now accepted.
        $this->cm->raw('INSERT INTO users (email, name, age) VALUES (?, ?, ?)', ['c@x.io', null, 1]);
        $null = $this->cm->raw('SELECT name FROM users WHERE email = ?', ['c@x.io'])->fetchColumn();
        self::assertNull($null);
    }

    public function testChangeColumnTypeRetypesDeclaration(): void
    {
        Schema::table('users', function (Blueprint $t): void {
            $t->text('age')->nullable()->change();
        });

        $info = $this->cm->raw("PRAGMA table_info('users')")->fetchAll(\PDO::FETCH_ASSOC);
        $age  = array_find($info, fn (array $c): bool => $c['name'] === 'age');
        self::assertNotNull($age);
        self::assertSame('TEXT', strtoupper((string) $age['type']));
        self::assertSame(0, (int) $age['notnull']);
    }

    public function testRebuildPreservesAutoIncrementPrimaryKey(): void
    {
        Schema::table('users', function (Blueprint $t): void {
            $t->string('name')->nullable()->change();
        });

        $this->cm->raw('INSERT INTO users (email, name, age) VALUES (?, ?, ?)', ['d@x.io', 'Dee', 5]);
        $id = (int) $this->cm->raw('SELECT id FROM users WHERE email = ?', ['d@x.io'])->fetchColumn();
        self::assertSame(3, $id);
    }

    public function testRebuildPreservesIndexesAndUnique(): void
    {
        Schema::table('users', function (Blueprint $t): void {
            $t->string('name')->nullable()->change();
        });

        $indexes = $this->cm->raw(
            "SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = 'users' AND sql IS NOT NULL",
        )->fetchAll(\PDO::FETCH_COLUMN);

        self::assertContains('users_name_index', $indexes);

        // UNIQUE on email still enforced.
        $this->expectException(\PDOException::class);
        $this->cm->raw('INSERT INTO users (email, name, age) VALUES (?, ?, ?)', ['a@x.io', 'Dup', 9]);
    }

    public function testUntouchedColumnsKeepConstraints(): void
    {
        Schema::table('users', function (Blueprint $t): void {
            $t->text('age')->nullable()->change();
        });

        // name stays NOT NULL after rebuilding for the age change.
        $this->expectException(\PDOException::class);
        $this->cm->raw('INSERT INTO users (email, name, age) VALUES (?, ?, ?)', ['e@x.io', null, 2]);
    }
}

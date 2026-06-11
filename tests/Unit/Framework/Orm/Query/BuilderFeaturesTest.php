<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Query;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Query\Builder;
use Silver\Orm\Query\Compiler;
use Silver\Orm\Query\Paginator;

/**
 * Behavioural coverage for the Phase E builder additions — upsert,
 * insertOrIgnore, union, paginate — against in-memory sqlite.
 */
final class BuilderFeaturesTest extends TestCase
{
    private ConnectionManager $cm;

    protected function setUp(): void
    {
        $this->cm = new ConnectionManager();
        $this->cm->connect('default', 'sqlite::memory:');
        $this->cm->setDefault('default');
        $this->cm->exec(
            'CREATE TABLE users (
                id     INTEGER PRIMARY KEY AUTOINCREMENT,
                email  TEXT NOT NULL UNIQUE,
                name   TEXT NOT NULL,
                visits INTEGER NOT NULL DEFAULT 0
            )'
        );
    }

    private function q(): Builder
    {
        return new Builder($this->cm, new Compiler($this->cm));
    }

    public function testUpsertInsertsThenUpdatesOnConflict(): void
    {
        $this->q()->from('users')->upsert(['email' => 'a@b', 'name' => 'Alice'], 'email');
        $this->q()->from('users')->upsert(['email' => 'a@b', 'name' => 'Alicia'], 'email');

        $rows = $this->q()->from('users')->get();
        self::assertCount(1, $rows);
        self::assertSame('Alicia', $rows[0]['name']);
    }

    public function testUpsertWithExplicitUpdateColumnsLeavesOthersAlone(): void
    {
        $this->q()->from('users')->insert(['email' => 'a@b', 'name' => 'Alice', 'visits' => 5]);

        $this->q()->from('users')->upsert(
            ['email' => 'a@b', 'name' => 'Ghost', 'visits' => 9],
            'email',
            ['visits'],
        );

        $row = $this->q()->from('users')->where('email', 'a@b')->first();
        self::assertSame('Alice', $row['name'], 'name not in updateColumns — must stay');
        self::assertSame(9, (int) $row['visits']);
    }

    public function testInsertOrIgnoreSkipsConflictingRows(): void
    {
        $this->q()->from('users')->insert(['email' => 'a@b', 'name' => 'Alice']);

        $inserted = $this->q()->from('users')->insertOrIgnore([
            ['email' => 'a@b', 'name' => 'Duplicate'],
            ['email' => 'c@d', 'name' => 'Bob'],
        ]);

        self::assertSame(1, $inserted);
        self::assertSame(2, $this->q()->from('users')->count());
        $row = $this->q()->from('users')->where('email', 'a@b')->first();
        self::assertSame('Alice', $row['name']);
    }

    public function testUnionMergesResultSets(): void
    {
        $this->cm->exec('CREATE TABLE admins (id INTEGER PRIMARY KEY, email TEXT, name TEXT, visits INTEGER DEFAULT 0)');
        $this->q()->from('users')->insert(['email' => 'a@b', 'name' => 'Alice']);
        $this->cm->exec("INSERT INTO admins (email, name) VALUES ('root@x', 'Root')");

        $admins = $this->q()->from('admins')->select(['email']);
        $emails = $this->q()->from('users')->select(['email'])->union($admins)->get();

        self::assertSame(['a@b', 'root@x'], array_column($emails, 'email'));
    }

    public function testPaginateReturnsWindowAndMeta(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->q()->from('users')->insert(['email' => "u{$i}@x", 'name' => "User {$i}"]);
        }

        $page = $this->q()->from('users')->orderBy('id')->paginate(perPage: 3, page: 2);

        self::assertInstanceOf(Paginator::class, $page);
        self::assertSame(7, $page->total);
        self::assertSame(3, $page->perPage);
        self::assertSame(2, $page->currentPage);
        self::assertSame(3, $page->lastPage);
        self::assertSame(['User 4', 'User 5', 'User 6'], array_column($page->items, 'name'));
        self::assertTrue($page->hasMorePages());
        self::assertFalse($page->onFirstPage());
        self::assertSame(3, count($page));
    }

    public function testPaginateBeyondLastPageIsEmptyButKeepsMeta(): void
    {
        $this->q()->from('users')->insert(['email' => 'a@b', 'name' => 'Alice']);

        $page = $this->q()->from('users')->paginate(perPage: 10, page: 5);

        self::assertTrue($page->isEmpty());
        self::assertSame(1, $page->total);
        self::assertSame(1, $page->lastPage);
    }

    public function testPaginatorJsonShape(): void
    {
        $this->q()->from('users')->insert(['email' => 'a@b', 'name' => 'Alice']);
        $page = $this->q()->from('users')->paginate(perPage: 10, page: 1);

        $json = json_decode(json_encode($page), true);
        self::assertSame(['data', 'total', 'per_page', 'current_page', 'last_page'], array_keys($json));
        self::assertSame('Alice', $json['data'][0]['name']);
    }

    public function testTerminalsDoNotCorruptTheBuilder(): void
    {
        $this->q()->from('users')->insert(['email' => 'a@b', 'name' => 'Alice']);
        $this->q()->from('users')->insert(['email' => 'c@d', 'name' => 'Bob']);

        $q = $this->q()->from('users')->orderBy('id');

        // count() clones internally — it must not overwrite the select
        // or clear the ordering on the original builder.
        self::assertSame(2, $q->count());
        self::assertSame(2, $q->count());

        $first = $q->first(); // must not pin LIMIT 1 onto $q
        self::assertSame('Alice', $first['name']);

        $rows = $q->get();
        self::assertCount(2, $rows);
        self::assertSame(['Alice', 'Bob'], array_column($rows, 'name'));
    }

    public function testForUpdateIsHarmlessNoOpOnSqlite(): void
    {
        $this->q()->from('users')->insert(['email' => 'a@b', 'name' => 'Alice']);

        $row = $this->q()->from('users')->where('email', 'a@b')->forUpdate()->first();
        self::assertSame('Alice', $row['name']);
    }
}

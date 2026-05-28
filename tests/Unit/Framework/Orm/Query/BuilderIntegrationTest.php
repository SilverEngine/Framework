<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Query;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Query\Builder;
use Silver\Orm\Query\Compiler;

/**
 * Full pipeline against an in-memory sqlite — Builder → Grammar →
 * Compiler → PDO. The pure-SQL assertions live in SqliteGrammarTest;
 * here we assert *behaviour* (rows returned, rowCount on writes,
 * lastInsertId, aggregates, chunked iteration).
 */
final class BuilderIntegrationTest extends TestCase
{
    private ConnectionManager $cm;

    protected function setUp(): void
    {
        $this->cm = new ConnectionManager();
        $this->cm->connect('default', 'sqlite::memory:');
        $this->cm->setDefault('default');
        $this->cm->exec(
            'CREATE TABLE users (
                id        INTEGER PRIMARY KEY AUTOINCREMENT,
                email     TEXT NOT NULL,
                name      TEXT NOT NULL,
                team_id   INTEGER,
                active    INTEGER NOT NULL DEFAULT 1,
                deleted_at TEXT
            )'
        );
    }

    private function q(): Builder
    {
        return new Builder($this->cm, new Compiler($this->cm));
    }

    public function testInsertGetIdAndSelectRoundTrip(): void
    {
        $id = $this->q()->from('users')->insertGetId(['email' => 'a@b', 'name' => 'Alice', 'team_id' => 1]);
        self::assertSame('1', $id);

        $row = $this->q()->from('users')->where('id', 1)->first();
        self::assertNotNull($row);
        self::assertSame('a@b',   $row['email']);
        self::assertSame('Alice', $row['name']);
    }

    public function testInsertMultipleRowsInOneStatement(): void
    {
        $inserted = $this->q()->from('users')->insert([
            ['email' => 'a@b', 'name' => 'Alice',   'team_id' => 1],
            ['email' => 'c@d', 'name' => 'Bob',     'team_id' => 1],
            ['email' => 'e@f', 'name' => 'Charlie', 'team_id' => 2],
        ]);
        self::assertSame(3, $inserted);
        self::assertSame(3, $this->q()->from('users')->count());
    }

    public function testWhereInAndCount(): void
    {
        $this->q()->from('users')->insert([
            ['email' => 'a@b', 'name' => 'A', 'team_id' => 1],
            ['email' => 'c@d', 'name' => 'B', 'team_id' => 2],
            ['email' => 'e@f', 'name' => 'C', 'team_id' => 3],
        ]);

        $count = $this->q()->from('users')->whereIn('team_id', [1, 2])->count();
        self::assertSame(2, $count);

        $rows = $this->q()->from('users')->whereIn('team_id', [1, 2])->orderBy('id')->get();
        self::assertCount(2, $rows);
        self::assertSame('A', $rows[0]['name']);
    }

    public function testEmptyWhereInReturnsZeroRows(): void
    {
        $this->q()->from('users')->insert(['email' => 'a@b', 'name' => 'A', 'team_id' => 1]);
        self::assertSame(0, $this->q()->from('users')->whereIn('id', [])->count());
    }

    public function testUpdateAndDeleteReportAffectedRows(): void
    {
        $this->q()->from('users')->insert([
            ['email' => 'a@b', 'name' => 'A', 'team_id' => 1, 'active' => 1],
            ['email' => 'c@d', 'name' => 'B', 'team_id' => 1, 'active' => 1],
        ]);

        $affected = $this->q()->from('users')->where('team_id', 1)->update(['active' => 0]);
        self::assertSame(2, $affected);

        $deleted = $this->q()->from('users')->where('active', 0)->delete();
        self::assertSame(2, $deleted);
    }

    public function testAggregatesSumAvgMinMax(): void
    {
        $this->q()->from('users')->insert([
            ['email' => 'a@b', 'name' => 'A', 'team_id' => 10],
            ['email' => 'c@d', 'name' => 'B', 'team_id' => 20],
            ['email' => 'e@f', 'name' => 'C', 'team_id' => 30],
        ]);

        self::assertSame(60,    $this->q()->from('users')->sum('team_id'));
        self::assertSame(20.0,  $this->q()->from('users')->avg('team_id'));
        // min/max return the underlying driver type — sqlite gives int
        // for INTEGER columns, string for TEXT. Tests pinning a
        // grammar-level normalisation would belong on the Model layer's
        // cast pipeline, not here.
        self::assertEquals(10,  $this->q()->from('users')->min('team_id'));
        self::assertEquals(30,  $this->q()->from('users')->max('team_id'));
    }

    public function testExistsAndDoesntExist(): void
    {
        self::assertTrue($this->q()->from('users')->doesntExist());
        $this->q()->from('users')->insert(['email' => 'a@b', 'name' => 'A', 'team_id' => 1]);
        self::assertTrue($this->q()->from('users')->exists());
    }

    public function testJoinReturnsMergedRows(): void
    {
        $this->cm->exec('CREATE TABLE teams (id INTEGER PRIMARY KEY, name TEXT)');
        $this->q()->from('teams')->insert([['id' => 1, 'name' => 'Red'], ['id' => 2, 'name' => 'Blue']]);
        $this->q()->from('users')->insert([
            ['email' => 'a@b', 'name' => 'A', 'team_id' => 1],
            ['email' => 'c@d', 'name' => 'B', 'team_id' => 2],
        ]);

        $rows = $this->q()->from('users')
            ->select(['users.name AS user_name', 'teams.name AS team_name'])
            ->join('teams', 'teams.id', '=', 'users.team_id')
            ->orderBy('users.id')
            ->get();

        self::assertCount(2, $rows);
        self::assertSame('A',   $rows[0]['user_name']);
        self::assertSame('Red', $rows[0]['team_name']);
    }

    public function testEachIteratesAllRowsInPages(): void
    {
        $rows = [];
        for ($i = 1; $i <= 25; $i++) {
            $rows[] = ['email' => "u{$i}@x", 'name' => "U{$i}", 'team_id' => 1];
        }
        $this->q()->from('users')->insert($rows);

        $seen = [];
        $this->q()->from('users')->orderBy('id')->each(10, function (array $row) use (&$seen): void {
            $seen[] = $row['name'];
        });

        self::assertCount(25, $seen);
        self::assertSame('U1',  $seen[0]);
        self::assertSame('U25', $seen[24]);
    }
}

<?php

namespace Tests\Unit\Framework\Database;

use PHPUnit\Framework\TestCase;
use PDO;
use Silver\Database\Db;
use Silver\Database\Query;

/**
 * Reproduces + locks the builder result-fetch path. Pre-fix every
 * default-style fetch throws `TypeError: class_exists(): int given`
 * because setQueryMode()/transformResult() call class_exists($style)
 * before checking $style is a string (prepareSelect() already guards
 * it correctly — this aligns the rest).
 */
class DbFetchTest extends TestCase
{
    protected function setUp(): void
    {
        $c = 'fetch_' . str_replace('.', '', uniqid('', true));
        Db::connect($c, 'sqlite::memory:');
        Db::setConnection($c);
        Db::exec('CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        Db::exec("INSERT INTO t (name) VALUES ('a'), ('b')");
    }

    public function testAllDefaultStyleReturnsObjects(): void
    {
        $rows = Query::select()->from('t')->all();
        $this->assertCount(2, $rows);
        $this->assertSame('a', $rows[0]->name);
        $this->assertSame('b', $rows[1]->name);
    }

    public function testGetReturnsFirstObject(): void
    {
        $row = Query::select()->from('t')->where('name', 'b')->get();
        $this->assertSame('b', $row->name);
    }

    public function testFirstReturnsObject(): void
    {
        $row = Query::select()->from('t')->first();
        $this->assertSame('a', $row->name);
    }

    public function testSingleReturnsFirstColumn(): void
    {
        $val = Query::select('name')->from('t')->single();
        $this->assertSame('a', $val);
    }

    public function testArrayStyleReturnsAssoc(): void
    {
        $rows = Query::select()->from('t')->all(['name']);
        $this->assertSame(['name' => 'a'], $rows[0]);
    }
}

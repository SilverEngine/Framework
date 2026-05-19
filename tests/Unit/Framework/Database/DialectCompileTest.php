<?php

namespace Tests\Unit\Framework\Database;

use PHPUnit\Framework\TestCase;
use Silver\Database\Db;
use Silver\Database\Query;

/**
 * Characterizes compiled SQL output under the SQLite dialect. Pins the
 * exact strings so the B1 dialect-Strategy refactor is provably
 * behaviour-preserving (the class-name-rewrite + driver fallback must
 * produce identical SQL).
 */
class DialectCompileTest extends TestCase
{
    protected function setUp(): void
    {
        Db::connect('b1_dialect', 'sqlite::memory:');
        Db::setConnection('b1_dialect');
    }

    public function testDriverIsSqlite(): void
    {
        $this->assertSame('sqlite', Db::driverName());
    }

    public function testSelectStar(): void
    {
        $this->assertSame(
            'SELECT * FROM `users`',
            Query::select()->from('users')->toSql(),
        );
    }

    public function testSelectColumnsWithWhere(): void
    {
        $this->assertSame(
            'SELECT `id`, `name` FROM `users` WHERE `id` = ?',
            Query::select('id', 'name')->from('users')->where('id', 1)->toSql(),
        );
    }

    public function testSelectWithLimit(): void
    {
        $this->assertSame(
            'SELECT * FROM `posts` LIMIT 5',
            Query::select()->from('posts')->limit(5)->toSql(),
        );
    }
}

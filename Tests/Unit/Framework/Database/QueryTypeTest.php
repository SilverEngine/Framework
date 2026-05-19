<?php

namespace Tests\Unit\Framework\Database;

use PHPUnit\Framework\TestCase;
use Silver\Database\Db;
use Silver\Database\Query;
use Silver\Database\Query\Select;
use Silver\Database\QueryType;

/**
 * Locks the QueryType factory: enum → query class mapping, and that
 * each query kind compiles to the exact SQL captured before the
 * stringly-typed Query::instance() was replaced.
 */
class QueryTypeTest extends TestCase
{
    protected function setUp(): void
    {
        Db::connect('b2_qt', 'sqlite::memory:');
        Db::setConnection('b2_qt');
    }

    public function testEnumResolvesLegacyQueryClass(): void
    {
        $this->assertSame('Silver\Database\Query\Select', QueryType::Select->queryClass());
        $this->assertSame('Silver\Database\Query\Insert', QueryType::Insert->queryClass());
        $this->assertSame('Silver\Database\Query\Alter', QueryType::Alter->queryClass());
        $this->assertInstanceOf(Select::class, QueryType::Select->make([['*']]));
    }

    public function testFactoryDispatchProducesUnchangedSql(): void
    {
        $this->assertSame(
            'INSERT INTO `users` (`name`) VALUES (?)',
            Query::insert('users', ['name' => 'Ann'])->toSql(),
        );
        $this->assertSame(
            'UPDATE `users` SET `name` = ? WHERE `id` = ?',
            Query::update('users', ['name' => 'Bob'])->where('id', 1)->toSql(),
        );
        $this->assertSame(
            'DELETE  FROM `users` WHERE `id` = ?',
            Query::delete()->from('users')->where('id', 1)->toSql(),
        );
        $this->assertSame(
            'DROP TABLE `users`',
            Query::drop('users')->toSql(),
        );
    }
}

<?php

namespace Tests\Unit\Framework\Database;

use PHPUnit\Framework\TestCase;
use PDO;
use Silver\Database\Db;
use Silver\Database\Query;

/**
 * Characterizes the full observable Db contract (connection registry,
 * raw exec/query, quoting, transactions incl. savepoint nesting,
 * fetch shapes) BEFORE the God-class split. These assertions must hold
 * identically after Db delegates to ConnectionManager/TransactionManager.
 *
 * Each test uses a unique connection name so the process-global static
 * registry + tx counter stay isolated.
 */
class DbBehaviorTest extends TestCase
{
    private string $conn;

    protected function setUp(): void
    {
        $this->conn = 'b4_' . str_replace('.', '', uniqid('', true));
        Db::connect($this->conn, 'sqlite::memory:');
        Db::setConnection($this->conn);
        Db::exec('CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    }

    public function testConnectionRegistry(): void
    {
        $this->assertContains($this->conn, Query::connections());
        $this->assertInstanceOf(PDO::class, Db::connection());
        $this->assertSame('sqlite', Db::driverName());
    }

    public function testExecAndLastInsertId(): void
    {
        $rows = Db::exec("INSERT INTO t (name) VALUES ('alice')");
        $this->assertSame(1, $rows);
        $this->assertSame('1', Db::lastInsertId());
    }

    public function testQuoteContract(): void
    {
        $this->assertSame("'abc'", Db::quote('abc'));
        $this->assertSame(5, Db::quote(5));
        $this->assertSame(2.5, Db::quote(2.5));

        $this->expectException(\Exception::class);
        Db::quote(['nope']);
    }

    public function testRawQueryFetchShapes(): void
    {
        Db::exec("INSERT INTO t (name) VALUES ('a'), ('b')");

        $all = Query::select()->from('t')->all();
        $this->assertCount(2, $all);
        $this->assertSame('a', $all[0]->name);

        $one = Query::select()->from('t')->where('name', 'b')->get();
        $this->assertSame('b', $one->name);

        $first = Query::select()->from('t')->first();
        $this->assertSame('a', $first->name);
    }

    public function testCommitPersistsRollbackReverts(): void
    {
        Db::beginTransaction();
        Db::exec("INSERT INTO t (name) VALUES ('committed')");
        Db::commit();
        $this->assertSame(1, count(Query::select()->from('t')->all()));

        Db::beginTransaction();
        Db::exec("INSERT INTO t (name) VALUES ('rolled')");
        Db::rollBack();
        $this->assertSame(1, count(Query::select()->from('t')->all()));
    }

    public function testTxCounterAndSavepointNesting(): void
    {
        $this->assertSame(0, Db::getTxCounter());

        Db::beginTransaction();
        $this->assertSame(1, Db::getTxCounter());
        Db::exec("INSERT INTO t (name) VALUES ('outer')");

        Db::beginTransaction();
        $this->assertSame(2, Db::getTxCounter());
        Db::exec("INSERT INTO t (name) VALUES ('inner')");
        Db::rollBack();
        $this->assertSame(1, Db::getTxCounter());

        Db::commit();
        $this->assertSame(0, Db::getTxCounter());

        $names = Query::select('name')->from('t')->all();
        $this->assertSame(['outer'], array_map(static fn ($r) => $r->name, $names));
    }

    public function testTransactionHelperCommitAndSuppressedRollback(): void
    {
        Db::transaction(function (): void {
            Db::exec("INSERT INTO t (name) VALUES ('ok')");
        });
        $this->assertSame(1, count(Query::select()->from('t')->all()));

        $ret = Db::transaction(function (): void {
            Db::exec("INSERT INTO t (name) VALUES ('bad')");
            throw new \Exception('boom');
        }, true);

        $this->assertFalse($ret);
        $this->assertSame(1, count(Query::select()->from('t')->all()));
    }

    public function testTransactionHelperRethrowsByDefault(): void
    {
        $this->expectException(\Exception::class);
        Db::transaction(function (): void {
            throw new \Exception('propagate');
        });
    }

    public function testWithConnectionScopesDefaultAndRestores(): void
    {
        $other = $this->conn . '_alt';
        Db::connect($other, 'sqlite::memory:');

        $seen = null;
        Db::withConnection($other, function () use (&$seen): void {
            $seen = Db::connection();
        });

        // After the closure the original default is restored: a query
        // against the original connection still sees table t.
        $this->assertInstanceOf(PDO::class, $seen);
        $this->assertSame(0, count(Query::select()->from('t')->all()));
    }
}

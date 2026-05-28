<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Connection;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Connection\TransactionManager;

final class TransactionManagerTest extends TestCase
{
    private ConnectionManager  $cm;
    private TransactionManager $tm;

    protected function setUp(): void
    {
        $this->cm = new ConnectionManager();
        $this->cm->connect('default', 'sqlite::memory:');
        $this->cm->setDefault('default');
        $this->cm->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)');

        $this->tm = new TransactionManager($this->cm);
    }

    public function testRunCommitsOnSuccess(): void
    {
        $this->tm->run(function (): void {
            $this->cm->raw('INSERT INTO t (name) VALUES (?)', ['alice']);
        });

        $count = (int) $this->cm->raw('SELECT COUNT(*) FROM t')->fetchColumn();
        self::assertSame(1, $count);
    }

    public function testRunRollsBackOnException(): void
    {
        try {
            $this->tm->run(function (): void {
                $this->cm->raw('INSERT INTO t (name) VALUES (?)', ['alice']);
                throw new RuntimeException('rollback me');
            });
            self::fail('expected throw');
        } catch (RuntimeException) {
        }

        $count = (int) $this->cm->raw('SELECT COUNT(*) FROM t')->fetchColumn();
        self::assertSame(0, $count);
    }

    public function testNestedSavepointsAreReleasedOnSuccess(): void
    {
        $this->tm->begin();
        $this->cm->raw('INSERT INTO t (name) VALUES (?)', ['outer']);

        $this->tm->begin();
        $this->cm->raw('INSERT INTO t (name) VALUES (?)', ['inner']);
        $this->tm->commit();

        $this->tm->commit();

        $count = (int) $this->cm->raw('SELECT COUNT(*) FROM t')->fetchColumn();
        self::assertSame(2, $count);
    }

    public function testNestedSavepointsRollBackInnerOnly(): void
    {
        $this->tm->begin();
        $this->cm->raw('INSERT INTO t (name) VALUES (?)', ['outer']);

        $this->tm->begin();
        $this->cm->raw('INSERT INTO t (name) VALUES (?)', ['inner']);
        $this->tm->rollBack();

        $this->tm->commit();

        $names = $this->cm->raw('SELECT name FROM t ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame(['outer'], $names);
    }

    public function testCommitWithoutBeginThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->tm->commit();
    }

    public function testCountersAreScopedPerConnection(): void
    {
        $this->cm->connect('audit', 'sqlite::memory:');

        $this->tm->begin('default');
        self::assertSame(1, $this->tm->level('default'));
        self::assertSame(0, $this->tm->level('audit'));

        $this->tm->begin('audit');
        self::assertSame(1, $this->tm->level('audit'));

        $this->tm->rollBack('default');
        $this->tm->rollBack('audit');
    }
}

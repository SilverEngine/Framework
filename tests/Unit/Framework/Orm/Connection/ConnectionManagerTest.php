<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Connection;

use PDO;
use PHPUnit\Framework\TestCase;
use Silver\Orm\Connection\ConnectionConfig;
use Silver\Orm\Connection\ConnectionException;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Connection\Driver;

final class ConnectionManagerTest extends TestCase
{
    private ConnectionManager $cm;

    protected function setUp(): void
    {
        $this->cm = new ConnectionManager();
        $this->cm->connect('default', 'sqlite::memory:');
        $this->cm->setDefault('default');
    }

    public function testPdoIsLazyAndMemoized(): void
    {
        $a = $this->cm->pdo();
        $b = $this->cm->pdo();
        self::assertSame($a, $b, 'pdo() must memoize after first resolution');
        self::assertInstanceOf(PDO::class, $a);
    }

    public function testDriverReturnsEnum(): void
    {
        self::assertSame(Driver::Sqlite, $this->cm->driver());
        self::assertSame('sqlite',       $this->cm->driverName());
    }

    public function testQuoteReturnsStringForEveryScalar(): void
    {
        self::assertSame("'abc'", $this->cm->quote('abc'));
        self::assertSame('5',     $this->cm->quote(5));
        self::assertSame('2.5',   $this->cm->quote(2.5));
        self::assertSame('1',     $this->cm->quote(true));
        self::assertSame('0',     $this->cm->quote(false));
        self::assertSame('NULL',  $this->cm->quote(null));
    }

    public function testQuoteThrowsOnUnsupportedType(): void
    {
        $this->expectException(ConnectionException::class);
        $this->cm->quote(['no']);
    }

    public function testUnregisteredConnectionThrows(): void
    {
        $this->expectException(ConnectionException::class);
        $this->cm->pdo('warehouse');
    }

    public function testSetDefaultThrowsForUnknown(): void
    {
        $this->expectException(ConnectionException::class);
        $this->cm->setDefault('warehouse');
    }

    public function testNoDefaultThrowsOnDefaultName(): void
    {
        $fresh = new ConnectionManager();
        $this->expectException(ConnectionException::class);
        $fresh->defaultName();
    }

    public function testWithConnectionScopesAndRestores(): void
    {
        $this->cm->connect('audit', 'sqlite::memory:');
        $observed = $this->cm->withConnection('audit', fn () => $this->cm->defaultName());
        self::assertSame('audit',   $observed);
        self::assertSame('default', $this->cm->defaultName(), 'default must be restored after the callback');
    }

    public function testWithConnectionRestoresOnException(): void
    {
        $this->cm->connect('audit', 'sqlite::memory:');
        try {
            $this->cm->withConnection('audit', function (): void {
                throw new \RuntimeException('boom');
            });
            self::fail('expected throw');
        } catch (\RuntimeException) {
        }
        self::assertSame('default', $this->cm->defaultName());
    }

    public function testRegisterConfigCarriesMigrationMetadata(): void
    {
        $this->cm->registerConfig('warehouse', new ConnectionConfig(
            driver:          Driver::Sqlite,
            dsn:             'sqlite::memory:',
            migrationsPath:  '/tmp/migrations/warehouse',
            migrationsTable: 'warehouse_migrations',
        ));
        $cfg = $this->cm->config('warehouse');
        self::assertNotNull($cfg);
        self::assertSame('/tmp/migrations/warehouse', $cfg->migrationsPath);
        self::assertSame('warehouse_migrations',      $cfg->migrationsTable);
        self::assertSame(Driver::Sqlite,              $cfg->driver);
    }

    public function testRawExecutesWithBindings(): void
    {
        $this->cm->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)');
        $this->cm->raw('INSERT INTO t (name) VALUES (?)', ['alice']);
        self::assertSame('1', $this->cm->lastInsertId());

        $stmt = $this->cm->raw('SELECT name FROM t WHERE id = ?', [1]);
        self::assertSame('alice', $stmt->fetchColumn());
    }

    public function testNamesReturnsRegistrationOrder(): void
    {
        $this->cm->connect('audit',     'sqlite::memory:');
        $this->cm->connect('warehouse', 'sqlite::memory:');
        self::assertSame(['default', 'audit', 'warehouse'], $this->cm->names());
    }
}

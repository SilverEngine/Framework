<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Schema;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Silver\Core\Env;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Connection\Driver;
use Silver\Orm\Schema\DatabaseBootstrap;

final class DatabaseBootstrapTest extends TestCase
{
    private const ROOT = '/srv/app';

    private mixed $savedEnvData;

    protected function setUp(): void
    {
        $prop = new ReflectionProperty(Env::class, 'envData');
        $this->savedEnvData = $prop->getValue();
    }

    protected function tearDown(): void
    {
        $prop = new ReflectionProperty(Env::class, 'envData');
        $prop->setValue(null, $this->savedEnvData);
    }

    /** Inject a `databases` config (null = no databases key at all). */
    private function setDatabases(?array $cfg): void
    {
        $data = $cfg === null
            ? new \stdClass()
            : json_decode(json_encode(['databases' => $cfg]));

        $prop = new ReflectionProperty(Env::class, 'envData');
        $prop->setValue(null, $data);
    }

    public function testBootRegistersNewShapeConnectionsAndDefault(): void
    {
        $this->setDatabases([
            'on'      => true,
            'default' => 'warehouse',
            'connections' => [
                'default'   => ['driver' => 'sqlite', 'database' => 'database/db.sqlite'],
                'warehouse' => [
                    'driver'   => 'pgsql',
                    'database' => 'wh',
                    'hostname' => 'db.example',
                    'port'     => 5433,
                    'username' => 'u',
                    'password' => 'p',
                ],
            ],
        ]);

        $cm = new ConnectionManager();
        $this->assertTrue(DatabaseBootstrap::boot($cm, self::ROOT));

        $this->assertSame(['default', 'warehouse'], $cm->names());
        $this->assertSame('warehouse', $cm->defaultName());

        $wh = $cm->config('warehouse');
        $this->assertSame(Driver::Pgsql, $wh->driver);
        $this->assertSame('pgsql:host=db.example;port=5433;dbname=wh', $wh->dsn);
        $this->assertSame('u', $wh->username);
        $this->assertSame('p', $wh->password);

        $def = $cm->config('default');
        $this->assertSame('sqlite:/srv/app/database/db.sqlite', $def->dsn);
    }

    public function testBootSupportsLegacyShape(): void
    {
        $this->setDatabases([
            'on'      => true,
            'default' => 'local',
            'local'   => ['driver' => 'sqlite', 'database' => 'database/db.sqlite'],
        ]);

        $cm = new ConnectionManager();
        $this->assertTrue(DatabaseBootstrap::boot($cm, self::ROOT));

        $this->assertSame(['local'], $cm->names());
        $this->assertSame('local', $cm->defaultName());
        $this->assertSame(Driver::Sqlite, $cm->config('local')->driver);
    }

    public function testBootHonoursOffToggle(): void
    {
        $this->setDatabases([
            'on'    => false,
            'local' => ['driver' => 'sqlite', 'database' => 'database/db.sqlite'],
        ]);

        $cm = new ConnectionManager();
        $this->assertFalse(DatabaseBootstrap::boot($cm, self::ROOT));
        $this->assertSame([], $cm->names());
    }

    public function testBootIsNoOpWithoutDatabasesConfig(): void
    {
        $this->setDatabases(null);

        $cm = new ConnectionManager();
        $this->assertFalse(DatabaseBootstrap::boot($cm, self::ROOT));
        $this->assertSame([], $cm->names());
    }

    public function testUnknownDefaultFallsBackToFirstConnection(): void
    {
        $this->setDatabases([
            'on'      => true,
            'default' => 'does-not-exist',
            'connections' => [
                'primary' => ['driver' => 'sqlite', 'database' => 'database/db.sqlite'],
            ],
        ]);

        $cm = new ConnectionManager();
        $this->assertTrue(DatabaseBootstrap::boot($cm, self::ROOT));
        $this->assertSame('primary', $cm->defaultName());
    }

    public function testMigrationsPathDefaultsPerConnection(): void
    {
        $this->setDatabases([
            'on' => true,
            'connections' => [
                'default'   => ['driver' => 'sqlite', 'database' => 'database/db.sqlite'],
                'warehouse' => ['driver' => 'sqlite', 'database' => 'database/wh.sqlite'],
            ],
        ]);

        $cm = new ConnectionManager();
        DatabaseBootstrap::boot($cm, self::ROOT);

        $this->assertSame('/srv/app/database/migrations', $cm->config('default')->migrationsPath);
        $this->assertSame('/srv/app/database/migrations/warehouse', $cm->config('warehouse')->migrationsPath);
    }

    public function testExplicitDsnAndMigrationsOverrides(): void
    {
        $this->setDatabases([
            'on' => true,
            'connections' => [
                'mem' => [
                    'driver'           => 'sqlite',
                    'dsn'              => 'sqlite::memory:',
                    'migrations'       => 'database/Migrations/special',
                    'migrations_table' => 'schema_log',
                ],
                'abs' => [
                    'driver'     => 'sqlite',
                    'dsn'        => 'sqlite::memory:',
                    'migrations' => '/opt/migrations',
                ],
            ],
        ]);

        $cm = new ConnectionManager();
        DatabaseBootstrap::boot($cm, self::ROOT);

        $mem = $cm->config('mem');
        $this->assertSame('sqlite::memory:', $mem->dsn);
        $this->assertSame('/srv/app/database/Migrations/special', $mem->migrationsPath);
        $this->assertSame('schema_log', $mem->migrationsTable);

        $this->assertSame('/opt/migrations', $cm->config('abs')->migrationsPath);
    }

    public function testMysqlDsnWithAndWithoutPort(): void
    {
        $this->setDatabases([
            'on' => true,
            'connections' => [
                'a' => ['driver' => 'mysql', 'database' => 'app', 'hostname' => 'h', 'port' => 3307],
                'b' => ['driver' => 'mysql', 'database' => 'app', 'host' => 'h2'],
            ],
        ]);

        $cm = new ConnectionManager();
        DatabaseBootstrap::boot($cm, self::ROOT);

        $this->assertSame('mysql:host=h;port=3307;dbname=app;charset=utf8mb4', $cm->config('a')->dsn);
        $this->assertSame('mysql:host=h2;dbname=app;charset=utf8mb4', $cm->config('b')->dsn);
    }
}

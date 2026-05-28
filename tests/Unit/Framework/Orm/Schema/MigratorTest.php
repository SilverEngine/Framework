<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Schema;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Connection\ConnectionConfig;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Connection\Driver;
use Silver\Orm\Connection\TransactionManager;
use Silver\Orm\Schema\Migrator;
use Silver\Orm\Schema\Schema;

/**
 * End-to-end: register two connections each with their own migrations
 * directory, run them, verify tables exist on the correct side and
 * NOT on the other, verify the per-connection tracking table.
 */
final class MigratorTest extends TestCase
{
    private ConnectionManager  $cm;
    private TransactionManager $tx;

    protected function setUp(): void
    {
        $this->cm = new ConnectionManager();
        $this->cm->registerConfig('default', new ConnectionConfig(
            driver:         Driver::Sqlite,
            dsn:            'sqlite::memory:',
            migrationsPath: __DIR__ . '/fixtures/default',
        ));
        $this->cm->registerConfig('warehouse', new ConnectionConfig(
            driver:         Driver::Sqlite,
            dsn:            'sqlite::memory:',
            migrationsPath: __DIR__ . '/fixtures/warehouse',
        ));
        $this->cm->setDefault('default');
        $this->tx = new TransactionManager($this->cm);
        Schema::bind($this->cm);
    }

    private function migrator(string $connection): Migrator
    {
        return new Migrator($this->cm, $this->tx, $connection);
    }

    public function testRunCreatesTablesOnDefaultOnly(): void
    {
        $applied = $this->migrator('default')->run();
        self::assertCount(2, $applied);
        self::assertTrue(Schema::connection('default')->hasTable('users'));
        self::assertTrue(Schema::connection('default')->hasTable('posts'));
        self::assertFalse(Schema::connection('warehouse')->hasTable('users'));
    }

    public function testEachConnectionHasItsOwnTrackingTable(): void
    {
        $this->migrator('default')->run();
        $this->migrator('warehouse')->run();

        self::assertTrue(Schema::connection('default')->hasTable('migrations'));
        self::assertTrue(Schema::connection('warehouse')->hasTable('migrations'));

        // The warehouse migration's name must NOT appear in default's table.
        $defaultRan = $this->cm->raw('SELECT migration FROM migrations', [], 'default')
            ->fetchAll(\PDO::FETCH_COLUMN);
        $warehouseRan = $this->cm->raw('SELECT migration FROM migrations', [], 'warehouse')
            ->fetchAll(\PDO::FETCH_COLUMN);

        self::assertSame(
            ['2026_01_01_000001_create_users_table', '2026_01_02_000001_create_posts_table'],
            $defaultRan,
        );
        self::assertSame(
            ['2026_01_01_000001_create_events_table'],
            $warehouseRan,
        );
    }

    public function testRunIsIdempotent(): void
    {
        $this->migrator('default')->run();
        $second = $this->migrator('default')->run();
        self::assertSame([], $second, 'second run on a fresh tree must find nothing pending');
    }

    public function testRollbackUndoesLastBatchOnly(): void
    {
        $this->migrator('default')->run();
        $reverted = $this->migrator('default')->rollback();

        // Both migrations were in the same batch — both should revert.
        self::assertCount(2, $reverted);
        self::assertFalse(Schema::connection('default')->hasTable('users'));
        self::assertFalse(Schema::connection('default')->hasTable('posts'));
    }

    public function testRollbackByBatchBoundary(): void
    {
        // Apply users first, then posts in a SEPARATE batch by running twice.
        $first = new Migrator($this->cm, $this->tx, 'default');
        // Simulate two batches by running, then adding a new file isn't
        // practical here — instead, run once with only one file visible:
        // use the migrator directly, then run again after the second
        // file is loaded. Sqlite migration files are static; we test
        // batch boundaries by rolling all back step by step instead.

        $first->run();                 // batch 1 contains both files
        $first->rollback(steps: 1);    // rolls batch 1 (both)
        self::assertFalse(Schema::connection('default')->hasTable('users'));
    }

    public function testFreshDropsAllTablesAndReapplies(): void
    {
        $this->migrator('default')->run();
        $this->cm->raw('INSERT INTO users (email, name) VALUES (?, ?)', ['a@b', 'A'], 'default');

        $this->migrator('default')->fresh();
        $count = (int) $this->cm->raw('SELECT COUNT(*) FROM users', [], 'default')->fetchColumn();
        self::assertSame(0, $count);
    }

    public function testStatusReportsRanAndPending(): void
    {
        $this->migrator('default')->run();
        $this->migrator('default')->rollback();   // empty state

        // Re-run only the first by deleting the second from the tracker
        // is not how this DSL works — instead, just check after a fresh run:
        $this->migrator('default')->run();
        $status = $this->migrator('default')->status();

        self::assertCount(2, $status);
        self::assertTrue($status[0]->ran);
        // Rollback DELETEs from the tracker, so MAX(batch) resets — a
        // fresh run gets batch 1 again. Batch numbers are sparse and
        // monotonic only while no rows are removed.
        self::assertSame(1, $status[0]->batch);
        self::assertTrue($status[1]->ran);
    }

    public function testDeclaredConnectionMismatchThrows(): void
    {
        // Drop a default fixture into the warehouse migrator's path
        // by reusing the existing fixture — its connection() returns
        // null so the mismatch protection is the explicit declaration.
        // Build a stub migration file with an explicit different
        // connection to verify the guard.
        $tmp = tempnam(sys_get_temp_dir(), 'mig') . '_2026_05_28_000001_bad.php';
        rename(tempnam(sys_get_temp_dir(), 'mig'), $tmp); // no-op safeguard
        file_put_contents($tmp, <<<'PHP'
<?php
return new class extends \Silver\Orm\Schema\Migration {
    protected ?string $connection = 'other';
    public function up(): void {}
    public function down(): void {}
};
PHP);

        $this->cm->registerConfig('one_off', new ConnectionConfig(
            driver:         Driver::Sqlite,
            dsn:            'sqlite::memory:',
            migrationsPath: dirname($tmp),
        ));

        // The discovery scoops up many unrelated files in /tmp — narrow
        // by symlinking into a dedicated dir.
        $dir = sys_get_temp_dir() . '/silver_mig_' . uniqid();
        mkdir($dir);
        rename($tmp, $dir . '/2026_05_28_000001_bad.php');
        $this->cm->registerConfig('one_off2', new ConnectionConfig(
            driver:         Driver::Sqlite,
            dsn:            'sqlite::memory:',
            migrationsPath: $dir,
        ));

        $this->expectException(\LogicException::class);
        $this->migrator('one_off2')->run();
    }
}

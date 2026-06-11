<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Cache\ModelCache;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Factory\Factory;
use Silver\Orm\Model\AttributeRegistry;
use Silver\Orm\Model\EventDispatcher;
use Silver\Orm\Model\Model;
use Silver\Orm\Seed\SeedRunner;
use Tests\Unit\Framework\Orm\Model\fixtures\MutatedUser;

final class MutatedUserFactory extends Factory
{
    protected string $model = MutatedUser::class;

    public function definition(): array
    {
        return [
            'email' => fn (int $i) => "user{$i}@example.test",
            'name'  => 'default name',
        ];
    }

    public function shouting(): static
    {
        return $this->state(fn (array $attrs, int $i) => ['name' => strtoupper($attrs['name'])]);
    }
}

final class FactorySeederTest extends TestCase
{
    private ConnectionManager $cm;
    private string $seedDir;

    protected function setUp(): void
    {
        AttributeRegistry::flush();
        ModelCache::flushAll();
        MutatedUser::unboot();

        $this->cm = new ConnectionManager();
        $this->cm->connect('default', 'sqlite::memory:');
        $this->cm->setDefault('default');
        $this->cm->exec(
            'CREATE TABLE users (
                id    INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                name  TEXT NOT NULL
            )',
        );
        Model::bind($this->cm, new EventDispatcher());

        $this->seedDir = sys_get_temp_dir() . '/silver-seeds-' . getmypid();
        @mkdir($this->seedDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->seedDir . '/*.php') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->seedDir);
    }

    // ---- factory ------------------------------------------------------

    public function testMakeBuildsUnsavedInstance(): void
    {
        $user = MutatedUserFactory::new()->make();

        self::assertInstanceOf(MutatedUser::class, $user);
        self::assertSame('user0@example.test', $user->email);
        self::assertFalse($user->exists());
        self::assertSame(0, $this->cm->raw('SELECT COUNT(*) FROM users')->fetchColumn() + 0);
    }

    public function testCreatePersistsCountInstancesWithUniqueClosureValues(): void
    {
        $users = MutatedUserFactory::new()->count(3)->create();

        self::assertCount(3, $users);
        self::assertSame(
            ['user0@example.test', 'user1@example.test', 'user2@example.test'],
            array_map(fn (MutatedUser $u) => $u->email, $users),
        );
        self::assertSame(3, (int) $this->cm->raw('SELECT COUNT(*) FROM users')->fetchColumn());
        self::assertTrue($users[0]->exists());
    }

    public function testStateAndOverridesLayerOverDefinition(): void
    {
        $user = MutatedUserFactory::new()
            ->shouting()
            ->make(['email' => 'fixed@x']);

        self::assertSame('DEFAULT NAME', $user->name);
        self::assertSame('fixed@x', $user->email);
    }

    public function testFactoryAssignmentRoutesThroughMutators(): void
    {
        // MutatedUser::setNameAttribute trims + ucwords.
        $user = MutatedUserFactory::new()->make(['name' => '  jane doe ']);

        self::assertSame('Jane Doe', $user->name);
    }

    // ---- seeder -------------------------------------------------------

    public function testSeedRunnerExecutesFilesInOrder(): void
    {
        file_put_contents($this->seedDir . '/02_second.php', <<<'PHP'
<?php
use Silver\Orm\Seed\Seeder;
use Tests\Unit\Framework\Orm\Model\fixtures\MutatedUser;

return new class extends Seeder {
    public function run(): void
    {
        MutatedUser::create(['email' => 'second@x', 'name' => 'Second']);
    }
};
PHP);
        file_put_contents($this->seedDir . '/01_first.php', <<<'PHP'
<?php
use Silver\Orm\Seed\Seeder;
use Tests\Unit\Framework\Orm\Model\fixtures\MutatedUser;

return new class extends Seeder {
    public function run(): void
    {
        MutatedUser::create(['email' => 'first@x', 'name' => 'First']);
    }
};
PHP);

        $ran = new SeedRunner()->run($this->seedDir);

        self::assertSame(['01_first.php', '02_second.php'], $ran);
        $names = $this->cm->raw('SELECT name FROM users ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN);
        self::assertSame(['First', 'Second'], $names);
    }

    public function testSeedRunnerOnMissingDirIsNoOp(): void
    {
        self::assertSame([], new SeedRunner()->run($this->seedDir . '/nope'));
    }

    public function testSeedFileNotReturningSeederThrows(): void
    {
        file_put_contents($this->seedDir . '/bad.php', "<?php\nreturn 42;\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('bad.php must return');
        new SeedRunner()->run($this->seedDir);
    }
}

<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Model;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Cache\ModelCache;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Model\AttributeRegistry;
use Silver\Orm\Model\EventDispatcher;
use Silver\Orm\Model\Model;
use Tests\Unit\Framework\Orm\Model\fixtures\User;
use Tests\Unit\Framework\Orm\Model\fixtures\UserObserver;

final class SoftDeleteScopesTest extends TestCase
{
    private ConnectionManager $cm;

    protected function setUp(): void
    {
        AttributeRegistry::flush();
        ModelCache::flushAll();
        UserObserver::$events = [];
        User::unboot();
        User::$bootCount = 0;

        $this->cm = new ConnectionManager();
        $this->cm->connect('default', 'sqlite::memory:');
        $this->cm->setDefault('default');
        $this->cm->exec(
            'CREATE TABLE users (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                email       TEXT NOT NULL UNIQUE,
                name        TEXT NOT NULL,
                password    TEXT NOT NULL DEFAULT "",
                role        TEXT NOT NULL DEFAULT "member",
                preferences TEXT,
                created_at  TEXT,
                updated_at  TEXT,
                deleted_at  TEXT
            )',
        );

        Model::bind($this->cm, new EventDispatcher());
    }

    /** @return array{0: User, 1: User} [alive, trashed] */
    private function seedOneAliveOneTrashed(): array
    {
        $alive   = User::create(['email' => 'alive@x', 'name' => 'Alive']);
        $trashed = User::create(['email' => 'gone@x', 'name' => 'Gone']);
        $trashed->delete();
        return [$alive, $trashed];
    }

    public function testDefaultScopeExcludesTrashed(): void
    {
        $this->seedOneAliveOneTrashed();

        $names = array_map(fn (User $u) => $u->name, User::query()->get());
        self::assertSame(['Alive'], $names);
    }

    public function testWithTrashedIncludesEverything(): void
    {
        $this->seedOneAliveOneTrashed();

        $rows = User::query()->withTrashed()->orderBy('id')->get();
        self::assertSame(['Alive', 'Gone'], array_map(fn (User $u) => $u->name, $rows));
    }

    public function testOnlyTrashedReturnsJustTrashed(): void
    {
        $this->seedOneAliveOneTrashed();

        $rows = User::query()->onlyTrashed()->get();
        self::assertSame(['Gone'], array_map(fn (User $u) => $u->name, $rows));
    }

    public function testCountAndPaginateRespectTheScope(): void
    {
        $this->seedOneAliveOneTrashed();

        self::assertSame(1, User::query()->count());
        self::assertSame(2, User::query()->withTrashed()->count());
        self::assertSame(1, User::query()->onlyTrashed()->count());

        $page = User::query()->withTrashed()->orderBy('id')->paginate(perPage: 10, page: 1);
        self::assertSame(2, $page->total);
    }

    public function testBulkUpdateAndDeleteAreScoped(): void
    {
        $this->seedOneAliveOneTrashed();

        // Bulk update must only touch non-trashed rows by default.
        User::query()->update(['role' => 'admin']);

        $roles = $this->cm->raw('SELECT name, role FROM users ORDER BY id')->fetchAll(\PDO::FETCH_KEY_PAIR);
        self::assertSame('admin', $roles['Alive']);
        self::assertSame('member', $roles['Gone'], 'trashed row must not be bulk-updated');
    }

    public function testTrashedAndRestoreRoundTrip(): void
    {
        [, $trashed] = $this->seedOneAliveOneTrashed();

        self::assertTrue($trashed->trashed());
        self::assertTrue($trashed->restore());
        self::assertFalse($trashed->trashed());

        // Visible again through the default scope.
        $restored = User::query()->where('email', 'gone@x')->first();
        self::assertNotNull($restored);
        self::assertNull(
            $this->cm->raw('SELECT deleted_at FROM users WHERE email = ?', ['gone@x'])->fetchColumn(),
        );

        // Restoring an already-alive row is a no-op success.
        self::assertTrue($restored->restore());
    }

    public function testRestoreDispatchesObserverEvents(): void
    {
        [, $trashed] = $this->seedOneAliveOneTrashed();

        UserObserver::$events = [];
        $trashed->restore();

        self::assertSame(['restoring:gone@x', 'restored:gone@x'], UserObserver::$events);
    }

    public function testFindAndFirstRespectScopeAndWithTrashedOverrides(): void
    {
        [, $trashed] = $this->seedOneAliveOneTrashed();

        self::assertNull(User::find($trashed->id));
        $found = User::query()->withTrashed()->find($trashed->id);
        self::assertNotNull($found);
        self::assertSame('Gone', $found->name);
    }
}

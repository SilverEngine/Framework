<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Model;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Silver\Orm\Cache\ModelCache;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Model\AttributeRegistry;
use Silver\Orm\Model\EventDispatcher;
use Silver\Orm\Model\Model;
use Silver\Orm\Model\ModelNotFound;
use Tests\Unit\Framework\Orm\Model\fixtures\User;
use Tests\Unit\Framework\Orm\Model\fixtures\UserObserver;
use Tests\Unit\Framework\Orm\Model\fixtures\UserRole;

final class ModelTest extends TestCase
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

    public function testCreateThenFindRoundTripWithCastsAndTimestamps(): void
    {
        $user = User::create([
            'email'       => 'a@b.com',
            'name'        => 'Alice',
            'preferences' => ['theme' => 'dark'],
        ]);

        self::assertSame(1, $user->id);
        self::assertNotNull($user->created_at);
        self::assertSame(UserRole::Member, $user->role);
        self::assertSame(['theme' => 'dark'], $user->preferences);

        $fresh = User::find(1);
        self::assertNotNull($fresh);
        self::assertSame('Alice', $fresh->name);
        self::assertInstanceOf(DateTimeImmutable::class, $fresh->created_at);
        self::assertSame(['theme' => 'dark'], $fresh->preferences);
        self::assertSame(UserRole::Member, $fresh->role);
    }

    public function testHiddenAttributeStripsFromToArray(): void
    {
        $u = User::create(['email' => 'a@b', 'name' => 'A']);
        $u->password = 'secret';
        $u->save();

        $arr = $u->toArray();
        self::assertArrayNotHasKey('password', $arr);
    }

    public function testFillableArrayBlocksGuardedColumns(): void
    {
        // 'id' isn't in $fillable, so mass-assignment must skip it.
        $u = User::create([
            'id'    => 999,
            'email' => 'a@b',
            'name'  => 'A',
        ]);
        self::assertSame(1, $u->id, 'id must come from autoincrement, not the input');
    }

    public function testDirtyTrackingAndUpdate(): void
    {
        $u = User::create(['email' => 'a@b', 'name' => 'A']);

        self::assertFalse($u->isDirty());
        $u->name = 'B';
        self::assertTrue($u->isDirty('name'));
        $u->save();

        $reloaded = User::find($u->id);
        self::assertSame('B', $reloaded->name);
    }

    public function testSoftDeleteHidesRowFromQuery(): void
    {
        $u = User::create(['email' => 'a@b', 'name' => 'A']);
        $u->delete();

        self::assertNull(User::find($u->id), 'soft-deleted row must not surface via default query');

        $raw = $this->cm->raw('SELECT deleted_at FROM users WHERE id = ?', [$u->id])->fetchColumn();
        self::assertNotEmpty($raw);
    }

    public function testForceDeleteRemovesRow(): void
    {
        $u = User::create(['email' => 'a@b', 'name' => 'A']);
        $u->forceDelete();

        $count = (int) $this->cm->raw('SELECT COUNT(*) FROM users')->fetchColumn();
        self::assertSame(0, $count);
    }

    public function testObserverReceivesLifecycleEvents(): void
    {
        $u = User::create(['email' => 'a@b', 'name' => 'A']);
        $u->name = 'B';
        $u->save();
        $u->delete();

        self::assertSame(
            ['creating:a@b', 'created:a@b', 'updating:a@b', 'updated:a@b', 'deleting:a@b', 'deleted:a@b'],
            UserObserver::$events,
        );
    }

    public function testBootRunsOnceLazily(): void
    {
        self::assertSame(0, User::$bootCount);
        User::query();
        User::query();
        User::find(1);
        self::assertSame(1, User::$bootCount, 'boot() must execute exactly once per class');
    }

    public function testIdentityMapCachesFindWithinRequest(): void
    {
        User::create(['email' => 'a@b', 'name' => 'A']);

        $a = User::find(1);
        $b = User::find(1);
        self::assertSame($a, $b, 'second find() in same request must return identical instance');
    }

    public function testSavingBustsCacheForThisPk(): void
    {
        $u = User::create(['email' => 'a@b', 'name' => 'A']);
        $cached = User::find($u->id);
        self::assertSame($u, $cached);

        $u->name = 'Alice';
        $u->save();

        $next = User::find($u->id);
        self::assertSame('Alice', $next->name, 'cache must reflect the post-update value');
    }

    public function testRepositoryForwardingViaStaticMagic(): void
    {
        User::create(['email' => 'a@b', 'name' => 'A']);
        $found = User::findByEmail('a@b');
        self::assertNotNull($found);
        self::assertSame('A', $found->name);
    }

    public function testSearchScopeAcrossSearchableColumns(): void
    {
        User::create(['email' => 'alice@x',   'name' => 'Alice']);
        User::create(['email' => 'bob@x',     'name' => 'Bob']);
        User::create(['email' => 'charlie@x', 'name' => 'Alice C.']);

        $hits = User::search('Alice')->all();
        self::assertCount(2, $hits);
    }

    public function testJsonSerializeEmitsIso8601ForDates(): void
    {
        $u = User::create(['email' => 'a@b', 'name' => 'A']);
        $json = json_encode($u);
        self::assertIsString($json);
        $decoded = json_decode($json, true);
        self::assertArrayHasKey('created_at', $decoded);
        // ISO 8601 with offset, e.g. 2026-05-28T05:00:12+00:00
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $decoded['created_at']);
    }

    public function testFindOrFailThrowsOnMiss(): void
    {
        $this->expectException(ModelNotFound::class);
        User::findOrFail(999);
    }
}

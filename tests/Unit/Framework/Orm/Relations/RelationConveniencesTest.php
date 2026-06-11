<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Relations;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Cache\ModelCache;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Model\AttributeRegistry;
use Silver\Orm\Model\EventDispatcher;
use Silver\Orm\Model\Model;
use Tests\Unit\Framework\Orm\Model\fixtures\User;
use Tests\Unit\Framework\Orm\Relations\fixtures\Member;
use Tests\Unit\Framework\Orm\Relations\fixtures\Team;

final class RelationConveniencesTest extends TestCase
{
    private ConnectionManager $cm;

    protected function setUp(): void
    {
        AttributeRegistry::flush();
        ModelCache::flushAll();
        Team::unboot();
        Member::unboot();
        User::unboot();

        $this->cm = new ConnectionManager();
        $this->cm->connect('default', 'sqlite::memory:');
        $this->cm->setDefault('default');
        $this->cm->exec('CREATE TABLE teams   (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $this->cm->exec('CREATE TABLE members (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, team_id INTEGER)');
        $this->cm->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL, password TEXT NOT NULL DEFAULT "",
                role TEXT NOT NULL DEFAULT "member", preferences TEXT,
                created_at TEXT, updated_at TEXT, deleted_at TEXT
            )',
        );

        Model::bind($this->cm, new EventDispatcher());
    }

    public function testAssociateSetsForeignKeyAndPersistsViaSave(): void
    {
        $team   = Team::create(['name' => 'Red']);
        $member = Member::create(['name' => 'Alice']);

        $member->team()->associate($team);
        self::assertSame($team->id, $member->team_id);

        $member->save();
        self::assertSame(
            $team->id,
            (int) $this->cm->raw('SELECT team_id FROM members WHERE id = ?', [$member->id])->fetchColumn(),
        );
        self::assertSame('Red', $member->team()->getResults()?->name);
    }

    public function testDissociateNullsForeignKey(): void
    {
        $team   = Team::create(['name' => 'Red']);
        $member = Member::create(['name' => 'Alice', 'team_id' => $team->id]);

        $member->team()->dissociate();
        $member->save();

        self::assertNull($member->team_id);
        self::assertNull(
            $this->cm->raw('SELECT team_id FROM members WHERE id = ?', [$member->id])->fetchColumn(),
        );
    }

    public function testTouchBumpsUpdatedAt(): void
    {
        $user = User::create(['email' => 'a@b', 'name' => 'Alice']);

        // Backdate, rehydrate, touch.
        $this->cm->raw('UPDATE users SET updated_at = ? WHERE id = ?', ['2000-01-01 00:00:00', $user->id]);
        ModelCache::flushAll();
        $stale = User::find($user->id);
        self::assertSame('2000', $stale->updated_at->format('Y'));

        self::assertTrue($stale->touch());

        $persisted = (string) $this->cm
            ->raw('SELECT updated_at FROM users WHERE id = ?', [$user->id])->fetchColumn();
        self::assertNotSame('2000-01-01 00:00:00', $persisted);
        self::assertSame(date('Y'), $stale->updated_at->format('Y'));
        self::assertFalse($stale->isDirty(), 'touch refreshes the baseline');
    }

    public function testTouchIsNoOpWithoutTimestampsOrUnsaved(): void
    {
        $team = Team::create(['name' => 'Red']);   // Team has no #[Timestamps]
        self::assertFalse($team->touch());

        $unsaved = new User();
        self::assertFalse($unsaved->touch());
    }
}

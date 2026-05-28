<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Relations;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Cache\ModelCache;
use Silver\Orm\Collection;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Model\AttributeRegistry;
use Silver\Orm\Model\EventDispatcher;
use Silver\Orm\Model\Model;
use Silver\Orm\Relations\LazyLoadingViolation;
use Tests\Unit\Framework\Orm\Relations\fixtures\Member;
use Tests\Unit\Framework\Orm\Relations\fixtures\Profile;
use Tests\Unit\Framework\Orm\Relations\fixtures\Tag;
use Tests\Unit\Framework\Orm\Relations\fixtures\Team;

final class RelationsTest extends TestCase
{
    private ConnectionManager $cm;

    protected function setUp(): void
    {
        AttributeRegistry::flush();
        ModelCache::flushAll();
        Team::unboot();
        Member::unboot();
        Profile::unboot();
        Tag::unboot();

        $this->cm = new ConnectionManager();
        $this->cm->connect('default', 'sqlite::memory:');
        $this->cm->setDefault('default');

        $this->cm->exec('CREATE TABLE teams    (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $this->cm->exec('CREATE TABLE members  (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, team_id INTEGER)');
        $this->cm->exec('CREATE TABLE profiles (id INTEGER PRIMARY KEY AUTOINCREMENT, member_id INTEGER, bio TEXT NOT NULL)');
        $this->cm->exec('CREATE TABLE tags     (id INTEGER PRIMARY KEY AUTOINCREMENT, label TEXT NOT NULL)');
        $this->cm->exec('CREATE TABLE member_tag (member_id INTEGER, tag_id INTEGER)');

        Model::bind($this->cm, new EventDispatcher());
    }

    private function seed(): array
    {
        $red  = Team::create(['name' => 'Red']);
        $blue = Team::create(['name' => 'Blue']);

        $alice   = Member::create(['name' => 'Alice',   'team_id' => $red->id]);
        $bob     = Member::create(['name' => 'Bob',     'team_id' => $red->id]);
        $charlie = Member::create(['name' => 'Charlie', 'team_id' => $blue->id]);

        Profile::create(['member_id' => $alice->id, 'bio' => 'Alice bio']);
        Profile::create(['member_id' => $bob->id,   'bio' => 'Bob bio']);

        $php = Tag::create(['label' => 'php']);
        $sql = Tag::create(['label' => 'sql']);

        $this->cm->exec('INSERT INTO member_tag (member_id, tag_id) VALUES (?, ?)', /* fall back */ );
        // use a tiny query helper for the pivot inserts:
        $this->cm->raw('INSERT INTO member_tag (member_id, tag_id) VALUES (?, ?)', [$alice->id, $php->id]);
        $this->cm->raw('INSERT INTO member_tag (member_id, tag_id) VALUES (?, ?)', [$alice->id, $sql->id]);
        $this->cm->raw('INSERT INTO member_tag (member_id, tag_id) VALUES (?, ?)', [$bob->id,   $php->id]);

        return compact('red', 'blue', 'alice', 'bob', 'charlie');
    }

    public function testBelongsToReturnsParentModel(): void
    {
        $s = $this->seed();
        $member = Member::find($s['alice']->id);

        $team = $member->team()->getResults();
        self::assertNotNull($team);
        self::assertSame('Red', $team->name);
    }

    public function testHasManyReturnsCollectionOfChildren(): void
    {
        $s = $this->seed();
        $team = Team::find($s['red']->id);

        $members = $team->members()->getResults();
        self::assertInstanceOf(Collection::class, $members);
        self::assertCount(2, $members);
    }

    public function testHasOneReturnsSingleChild(): void
    {
        $s = $this->seed();
        $member = Member::find($s['alice']->id);

        $profile = $member->profile()->getResults();
        self::assertNotNull($profile);
        self::assertSame('Alice bio', $profile->bio);
    }

    public function testLazyRelationAccessThrows(): void
    {
        $s = $this->seed();
        $team = Team::find($s['red']->id);

        $this->expectException(LazyLoadingViolation::class);
        $team->members; // no ->with('members'), no relation explicitly loaded
    }

    public function testEagerLoadingPopulatesRelation(): void
    {
        $s = $this->seed();

        /** @var list<Team> $teams */
        $teams = Team::query()->with('members')->all();

        // Property access on an eager-loaded relation returns the
        // attached value, not a lazy throw.
        foreach ($teams as $t) {
            self::assertInstanceOf(Collection::class, $t->members);
        }

        $byName = [];
        foreach ($teams as $t) {
            $byName[$t->name] = $t;
        }
        self::assertCount(2, $byName['Red']->members);
        self::assertCount(1, $byName['Blue']->members);
    }

    public function testEagerLoadingViaBelongsToAttachesParent(): void
    {
        $s = $this->seed();
        /** @var list<Member> $members */
        $members = Member::query()->with('team')->orderBy('id')->all();

        self::assertSame('Red',  $members[0]->team->name);
        self::assertSame('Red',  $members[1]->team->name);
        self::assertSame('Blue', $members[2]->team->name);
    }

    public function testNestedEagerLoading(): void
    {
        $s = $this->seed();
        /** @var list<Team> $teams */
        $teams = Team::query()->with('members.profile')->all();

        $red = null;
        foreach ($teams as $t) {
            if ($t->name === 'Red') {
                $red = $t;
            }
        }
        self::assertNotNull($red);

        foreach ($red->members as $m) {
            // profile() relation must have been eager-loaded.
            self::assertNotNull($m->profile, 'nested eager-load must populate ->profile');
        }
    }

    public function testBelongsToManyAttachAndDetach(): void
    {
        $s = $this->seed();
        $alice = Member::find($s['alice']->id);

        $tags = $alice->tags()->getResults();
        self::assertCount(2, $tags, 'seeded pivot rows must surface');

        $newTag = Tag::create(['label' => 'js']);
        $alice->tags()->attach($newTag->id);

        $tags = $alice->tags()->getResults();
        self::assertCount(3, $tags);

        $alice->tags()->detach($newTag->id);
        self::assertCount(2, $alice->tags()->getResults());
    }

    public function testEagerLoadingViaBelongsToMany(): void
    {
        $s = $this->seed();
        /** @var list<Member> $members */
        $members = Member::query()->with('tags')->orderBy('id')->all();

        self::assertCount(2, $members[0]->tags);   // Alice
        self::assertCount(1, $members[1]->tags);   // Bob
        self::assertCount(0, $members[2]->tags);   // Charlie
    }
}

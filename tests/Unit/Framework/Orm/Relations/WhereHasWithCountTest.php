<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Relations;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Cache\ModelCache;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Model\AttributeRegistry;
use Silver\Orm\Model\EventDispatcher;
use Silver\Orm\Model\Model;
use Silver\Orm\Model\ModelBuilder;
use Tests\Unit\Framework\Orm\Relations\fixtures\Member;
use Tests\Unit\Framework\Orm\Relations\fixtures\Profile;
use Tests\Unit\Framework\Orm\Relations\fixtures\Tag;
use Tests\Unit\Framework\Orm\Relations\fixtures\Team;

final class WhereHasWithCountTest extends TestCase
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

    private function seed(): void
    {
        $red   = Team::create(['name' => 'Red']);
        $blue  = Team::create(['name' => 'Blue']);
        Team::create(['name' => 'Empty']);

        $alice   = Member::create(['name' => 'Alice',   'team_id' => $red->id]);
        $bob     = Member::create(['name' => 'Bob',     'team_id' => $red->id]);
        Member::create(['name' => 'Charlie', 'team_id' => $blue->id]);

        Profile::create(['member_id' => $alice->id, 'bio' => 'Alice bio']);
        Profile::create(['member_id' => $bob->id,   'bio' => 'Bob bio']);

        $php = Tag::create(['label' => 'php']);
        $sql = Tag::create(['label' => 'sql']);
        $this->cm->raw('INSERT INTO member_tag (member_id, tag_id) VALUES (?, ?)', [$alice->id, $php->id]);
        $this->cm->raw('INSERT INTO member_tag (member_id, tag_id) VALUES (?, ?)', [$alice->id, $sql->id]);
        $this->cm->raw('INSERT INTO member_tag (member_id, tag_id) VALUES (?, ?)', [$bob->id,   $php->id]);
    }

    /** @param list<Model> $models */
    private function names(array $models): array
    {
        return array_map(fn (Model $m) => $m->name, $models);
    }

    public function testWhereHasOnHasMany(): void
    {
        $this->seed();

        $teams = Team::query()->whereHas('members')->orderBy('id')->get();
        self::assertSame(['Red', 'Blue'], $this->names($teams));
    }

    public function testWhereHasWithConstraint(): void
    {
        $this->seed();

        $teams = Team::query()
            ->whereHas('members', fn (ModelBuilder $q) => $q->where('name', 'Alice'))
            ->get();

        self::assertSame(['Red'], $this->names($teams));
    }

    public function testWhereDoesntHave(): void
    {
        $this->seed();

        $teams = Team::query()->whereDoesntHave('members')->get();
        self::assertSame(['Empty'], $this->names($teams));
    }

    public function testWhereHasOnHasOneAndBelongsTo(): void
    {
        $this->seed();

        $withProfile = Member::query()->whereHas('profile')->orderBy('id')->get();
        self::assertSame(['Alice', 'Bob'], $this->names($withProfile));

        $onBlue = Member::query()
            ->whereHas('team', fn (ModelBuilder $q) => $q->where('name', 'Blue'))
            ->get();
        self::assertSame(['Charlie'], $this->names($onBlue));
    }

    public function testWhereHasOnBelongsToMany(): void
    {
        $this->seed();

        $tagged = Member::query()->whereHas('tags')->orderBy('id')->get();
        self::assertSame(['Alice', 'Bob'], $this->names($tagged));

        $sqlPeople = Member::query()
            ->whereHas('tags', fn (ModelBuilder $q) => $q->where('label', 'sql'))
            ->get();
        self::assertSame(['Alice'], $this->names($sqlPeople));
    }

    public function testWithCountProjectsRelationCounts(): void
    {
        $this->seed();

        $teams = Team::query()->withCount('members')->orderBy('id')->get();

        self::assertSame(['Red' => 2, 'Blue' => 1, 'Empty' => 0], array_combine(
            $this->names($teams),
            array_map(fn (Team $t) => (int) $t->members_count, $teams),
        ));

        // Base columns still hydrate normally alongside the count.
        self::assertSame('Red', $teams[0]->name);
        self::assertNotNull($teams[0]->id);
    }

    public function testWithCountMultipleRelations(): void
    {
        $this->seed();

        $alice = Member::query()->withCount('tags')->where('name', 'Alice')->first();
        self::assertSame(2, (int) $alice->tags_count);
    }

    public function testWhereHasUnknownRelationThrows(): void
    {
        $this->expectException(\BadMethodCallException::class);
        Team::query()->whereHas('nonsense')->get();
    }

    public function testWhereHasCombinesWithCount(): void
    {
        $this->seed();

        self::assertSame(2, Team::query()->whereHas('members')->count());
        self::assertSame(1, Team::query()->whereDoesntHave('members')->count());
    }
}

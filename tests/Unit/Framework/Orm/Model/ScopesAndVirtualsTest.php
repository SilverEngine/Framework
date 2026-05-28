<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Model;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Attributes\GlobalScope;
use Silver\Orm\Attributes\PrimaryKey;
use Silver\Orm\Attributes\Scope;
use Silver\Orm\Attributes\Table;
use Silver\Orm\Cache\ModelCache;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Contracts\GlobalScopeInterface;
use Silver\Orm\Model\AttributeRegistry;
use Silver\Orm\Model\EventDispatcher;
use Silver\Orm\Model\Model;
use Silver\Orm\Query\Builder;

// ---------- fixtures ----------

final class PublishedScope implements GlobalScopeInterface
{
    public function apply(Builder $query): void
    {
        $query->where('published', true);
    }
}

#[Table('articles')]
#[GlobalScope(PublishedScope::class)]
final class Article extends Model
{
    protected static array $fillable = ['title', 'published', 'position'];
    protected static array $appends  = ['display_title'];

    #[PrimaryKey]
    public ?int $id = null;

    public string $title     = '';
    public bool   $published = false;
    public int    $position  = 0;

    #[Scope]
    public function topPositions(Builder $q, int $n): Builder
    {
        return $q->orderBy('position')->limit($n);
    }

    public function getDisplayTitleAttribute(): string
    {
        return strtoupper($this->title);
    }
}

// ---------- tests ----------

final class ScopesAndVirtualsTest extends TestCase
{
    private ConnectionManager $cm;

    protected function setUp(): void
    {
        AttributeRegistry::flush();
        ModelCache::flushAll();
        Article::unboot();

        $this->cm = new ConnectionManager();
        $this->cm->connect('default', 'sqlite::memory:');
        $this->cm->setDefault('default');
        $this->cm->exec('CREATE TABLE articles (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, published INTEGER NOT NULL DEFAULT 0, position INTEGER NOT NULL DEFAULT 0)');

        Model::bind($this->cm, new EventDispatcher());
    }

    public function testGlobalScopeFiltersByDefault(): void
    {
        $this->cm->raw('INSERT INTO articles (title, published, position) VALUES (?, 0, 1), (?, 1, 2), (?, 1, 3)',
            ['draft', 'live A', 'live B']);

        $rows = Article::query()->orderBy('position')->all();
        self::assertCount(2, $rows, 'global PublishedScope must hide the draft');
        self::assertSame('live A', $rows[0]->title);
    }

    public function testWithoutGlobalScopeSeesEverything(): void
    {
        $this->cm->raw('INSERT INTO articles (title, published, position) VALUES (?, 0, 1), (?, 1, 2)',
            ['draft', 'live']);

        $rows = Article::query()->withoutGlobalScope(PublishedScope::class)->all();
        self::assertCount(2, $rows);
    }

    public function testLocalScopeDispatchedViaMagicCall(): void
    {
        $this->cm->raw('INSERT INTO articles (title, published, position) VALUES (?, 1, 3), (?, 1, 1), (?, 1, 2)',
            ['c', 'a', 'b']);

        $rows = Article::query()->topPositions(2)->all();
        self::assertCount(2, $rows);
        self::assertSame('a', $rows[0]->title);
        self::assertSame('b', $rows[1]->title);
    }

    public function testAccessorBackedVirtualFieldShowsInToArray(): void
    {
        $a = Article::create(['title' => 'hello', 'published' => true]);
        $arr = $a->toArray();

        self::assertSame('hello', $arr['title']);
        self::assertSame('HELLO', $arr['display_title'], 'accessor-backed append must surface');
    }

    public function testAdHocAppendAttribute(): void
    {
        $a = Article::create(['title' => 'hello', 'published' => true]);
        $a->appendAttribute('custom_flag', 'yes');
        $arr = $a->toArray();

        self::assertSame('yes', $arr['custom_flag']);
        self::assertArrayNotHasKey('custom_flag', json_decode(
            json_encode($a->withoutAttribute('custom_flag')),
            true,
        ));
    }

    public function testInsertBeforeShiftsExistingRows(): void
    {
        // Seed three rows at positions 1, 2, 3.
        $a = Article::create(['title' => 'A', 'published' => true, 'position' => 1]);
        $b = Article::create(['title' => 'B', 'published' => true, 'position' => 2]);
        $c = Article::create(['title' => 'C', 'published' => true, 'position' => 3]);

        $new = new Article();
        $new->title     = 'X';
        $new->published = true;
        $new->insertBefore($b);   // X should land at 2, B→3, C→4

        $rows = Article::query()->orderBy('position')->all();
        $titles = array_map(fn ($r) => $r->title . '@' . $r->position, $rows);
        self::assertSame(['A@1', 'X@2', 'B@3', 'C@4'], $titles);
    }

    public function testInsertAfterShiftsCorrectly(): void
    {
        $a = Article::create(['title' => 'A', 'published' => true, 'position' => 1]);
        $b = Article::create(['title' => 'B', 'published' => true, 'position' => 2]);

        $new = new Article();
        $new->title     = 'X';
        $new->published = true;
        $new->insertAfter($a);  // X→2, B→3

        $rows = Article::query()->orderBy('position')->all();
        $titles = array_map(fn ($r) => $r->title . '@' . $r->position, $rows);
        self::assertSame(['A@1', 'X@2', 'B@3'], $titles);
    }
}

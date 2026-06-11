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
use Silver\Orm\Relations\Morph\TypeMap;
use Tests\Unit\Framework\Orm\Relations\fixtures\Label;
use Tests\Unit\Framework\Orm\Relations\fixtures\Post;
use Tests\Unit\Framework\Orm\Relations\fixtures\Video;

final class MorphToManyTest extends TestCase
{
    private ConnectionManager $cm;

    protected function setUp(): void
    {
        AttributeRegistry::flush();
        ModelCache::flushAll();
        TypeMap::flush();
        Post::unboot();
        Video::unboot();
        Label::unboot();

        TypeMap::register('post',  Post::class);
        TypeMap::register('video', Video::class);

        $this->cm = new ConnectionManager();
        $this->cm->connect('default', 'sqlite::memory:');
        $this->cm->setDefault('default');

        $this->cm->exec('CREATE TABLE posts  (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL)');
        $this->cm->exec('CREATE TABLE videos (id INTEGER PRIMARY KEY AUTOINCREMENT, url TEXT NOT NULL)');
        $this->cm->exec('CREATE TABLE labels (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
        $this->cm->exec(
            'CREATE TABLE labelables (label_id INTEGER, labelable_id INTEGER, labelable_type TEXT)',
        );

        Model::bind($this->cm, new EventDispatcher());
    }

    protected function tearDown(): void
    {
        TypeMap::flush();
    }

    /** @return array{post: Post, post2: Post, video: Video, news: Label, tech: Label} */
    private function seed(): array
    {
        $post  = Post::create(['title' => 'Hello']);
        $post2 = Post::create(['title' => 'Second']);
        $video = Video::create(['url' => 'v.mp4']);

        $news = Label::create(['name' => 'news']);
        $tech = Label::create(['name' => 'tech']);

        $pivot = fn (int $labelId, int $id, string $type) => $this->cm->raw(
            'INSERT INTO labelables (label_id, labelable_id, labelable_type) VALUES (?, ?, ?)',
            [$labelId, $id, $type],
        );
        $pivot($news->id, $post->id,  'post');
        $pivot($tech->id, $post->id,  'post');
        $pivot($news->id, $video->id, 'video');
        // A row whose labelable_id collides with $post->id but belongs
        // to a video — must never leak into the post's labels.
        $pivot($tech->id, $post->id, 'video');

        return compact('post', 'post2', 'video', 'news', 'tech');
    }

    public function testMorphToManyReturnsOnlyOwnTypeRows(): void
    {
        $s = $this->seed();

        $labels = Post::find($s['post']->id)->labels()->getResults();

        self::assertInstanceOf(Collection::class, $labels);
        self::assertCount(2, $labels);
        $names = [];
        foreach ($labels as $l) { $names[] = $l->name; }
        sort($names);
        self::assertSame(['news', 'tech'], $names);
    }

    public function testMorphToManyOnSecondTypeSharesPivotTable(): void
    {
        $s = $this->seed();

        $labels = Video::find($s['video']->id)->labels()->getResults();

        // video #1 has 'news' directly plus the 'tech' row whose
        // labelable_id happens to equal the post's id — only rows with
        // labelable_id = video->id count. post->id === video->id === 1
        // in a fresh sqlite db, so guard via type+id pairing.
        $names = [];
        foreach ($labels as $l) { $names[] = $l->name; }
        sort($names);
        self::assertSame(['news', 'tech'], $names);
    }

    public function testMorphedByManyReturnsParentsOfOneTypeOnly(): void
    {
        $s = $this->seed();

        $posts = Label::find($s['news']->id)->posts()->getResults();

        self::assertCount(1, $posts);
        self::assertSame('Hello', $posts[0]->title);

        $videos = Label::find($s['news']->id)->videos()->getResults();
        self::assertCount(1, $videos);
        self::assertSame('v.mp4', $videos[0]->url);
    }

    public function testAttachWritesTypeColumn(): void
    {
        $s = $this->seed();

        $s['post2']->labels()->attach($s['news']->id);

        $row = $this->cm->raw(
            'SELECT * FROM labelables WHERE labelable_id = ? AND label_id = ?',
            [$s['post2']->id, $s['news']->id],
        )->fetch(\PDO::FETCH_ASSOC);

        self::assertNotFalse($row);
        self::assertSame('post', $row['labelable_type']);
    }

    public function testDetachOnlyRemovesOwnTypeRows(): void
    {
        $s = $this->seed();

        // post #1 detaches 'tech' — the video pivot row sharing the
        // same labelable_id must survive.
        Post::find($s['post']->id)->labels()->detach($s['tech']->id);

        $count = (int) $this->cm->raw(
            'SELECT COUNT(*) FROM labelables WHERE label_id = ? AND labelable_type = ?',
            [$s['tech']->id, 'video'],
        )->fetchColumn();
        self::assertSame(1, $count);

        $labels = Post::find($s['post']->id)->labels()->getResults();
        self::assertCount(1, $labels);
        self::assertSame('news', $labels[0]->name);
    }

    public function testSyncAttachesAndDetachesWithinType(): void
    {
        $s = $this->seed();

        $result = Post::find($s['post']->id)->labels()->sync([$s['tech']->id]);

        self::assertSame([$s['news']->id], array_map(intval(...), $result['detached']));
        self::assertSame([], $result['attached']); // tech already attached

        $labels = Post::find($s['post']->id)->labels()->getResults();
        self::assertCount(1, $labels);
        self::assertSame('tech', $labels[0]->name);

        // Video rows untouched by the post's sync.
        $videoLabels = Video::find($s['video']->id)->labels()->getResults();
        self::assertCount(2, $videoLabels);
    }

    public function testEagerLoadingGroupsPerParentAndType(): void
    {
        $this->seed();

        /** @var list<Post> $posts */
        $posts = Post::query()->orderBy('id')->with('labels')->all();

        self::assertCount(2, $posts[0]->labels);
        self::assertCount(0, $posts[1]->labels);

        /** @var list<Video> $videos */
        $videos = Video::query()->with('labels')->all();
        self::assertCount(2, $videos[0]->labels);
    }

    public function testWhereHasThroughMorphToMany(): void
    {
        $s = $this->seed();

        $titles = Post::query()
            ->whereHas('labels', fn ($q) => $q->where('name', 'news'))
            ->pluck('title');

        self::assertSame(['Hello'], $titles);

        // 'tech' is pivoted to post #1 (type post) and post-id-colliding
        // video row — whereHas must respect the type column.
        $withTech = Post::query()->whereHas('labels', fn ($q) => $q->where('name', 'tech'))->pluck('title');
        self::assertSame(['Hello'], $withTech);
    }
}

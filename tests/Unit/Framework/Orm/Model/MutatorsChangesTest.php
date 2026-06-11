<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Model;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Cache\ModelCache;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Model\AttributeRegistry;
use Silver\Orm\Model\EventDispatcher;
use Silver\Orm\Model\Model;
use Tests\Unit\Framework\Orm\Model\fixtures\MutatedUser;

final class MutatorsChangesTest extends TestCase
{
    private ConnectionManager $cm;

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
    }

    // ---- mutators -----------------------------------------------------

    public function testFillRoutesThroughMutator(): void
    {
        $u = MutatedUser::create(['email' => 'a@b', 'name' => '  alice smith ']);

        self::assertSame('Alice Smith', $u->name);
        self::assertSame(
            'Alice Smith',
            $this->cm->raw('SELECT name FROM users WHERE id = ?', [$u->id])->fetchColumn(),
        );
    }

    public function testSetAttributeAppliesMutatorDirectly(): void
    {
        $u = new MutatedUser();
        $u->setAttribute('name', 'bob jones');

        self::assertSame('Bob Jones', $u->name);
    }

    public function testSetAttributeWithoutMutatorAssignsVerbatim(): void
    {
        $u = new MutatedUser();
        $u->setAttribute('email', 'RAW@CASE');

        self::assertSame('RAW@CASE', $u->email);
    }

    public function testDirectPropertyWriteBypassesMutatorByDesign(): void
    {
        $u = new MutatedUser();
        $u->name = '  raw  ';

        self::assertSame('  raw  ', $u->name);
    }

    public function testAccessorAndMutatorAreSymmetric(): void
    {
        $u = MutatedUser::create(['email' => 'a@b', 'name' => 'alice smith']);

        // Mutator normalised on the way in; accessor projects on the way out.
        self::assertSame('Alice Smith', $u->name);
        self::assertSame('ALICE SMITH', $u->toArray()['shout_name']);
    }

    // ---- change tracking ------------------------------------------------

    public function testGetChangesEmptyAfterCreate(): void
    {
        $u = MutatedUser::create(['email' => 'a@b', 'name' => 'Alice']);

        self::assertSame([], $u->getChanges());
        self::assertFalse($u->wasChanged());
    }

    public function testDirtyThenSaveMovesIntoChanges(): void
    {
        $u = MutatedUser::create(['email' => 'a@b', 'name' => 'Alice']);

        $u->name = 'Alicia';
        self::assertTrue($u->isDirty('name'));
        self::assertSame('Alice', $u->getOriginal('name'), 'original keeps pre-save value');
        self::assertFalse($u->wasChanged('name'), 'not yet saved');

        $u->save();

        self::assertFalse($u->isDirty(), 'baseline refreshed after save');
        self::assertTrue($u->wasChanged('name'));
        self::assertFalse($u->wasChanged('email'));
        self::assertSame(['name' => 'Alicia'], $u->getChanges());
        self::assertSame('Alicia', $u->getOriginal('name'));
    }

    public function testSecondSaveOverwritesChanges(): void
    {
        $u = MutatedUser::create(['email' => 'a@b', 'name' => 'Alice']);

        $u->name = 'Alicia';
        $u->save();

        $u->email = 'new@b';
        $u->save();

        self::assertSame(['email' => 'new@b'], $u->getChanges());
        self::assertFalse($u->wasChanged('name'));
    }
}

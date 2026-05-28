<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Schema\Grammar;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Schema\Blueprint;
use Silver\Orm\Schema\Grammar\SqliteSchemaGrammar;

final class SqliteSchemaGrammarTest extends TestCase
{
    private SqliteSchemaGrammar $g;

    protected function setUp(): void
    {
        $this->g = new SqliteSchemaGrammar();
    }

    public function testCreateMinimalTableWithId(): void
    {
        $bp = new Blueprint('users');
        $bp->id();
        $bp->string('email');

        $stmts = $this->g->compileCreate($bp);
        self::assertCount(1, $stmts);
        self::assertSame(
            'CREATE TABLE "users" ("id" INTEGER PRIMARY KEY AUTOINCREMENT, "email" TEXT NOT NULL)',
            $stmts[0],
        );
    }

    public function testStringWithLengthDefaultAndNullable(): void
    {
        $bp = new Blueprint('users');
        $bp->string('name', 64)->nullable()->default('anon');

        $stmts = $this->g->compileCreate($bp);
        self::assertSame(
            'CREATE TABLE "users" ("name" TEXT DEFAULT \'anon\')',
            $stmts[0],
        );
    }

    public function testEnumIsTextOnSqlite(): void
    {
        $bp = new Blueprint('users');
        $bp->enum('role', ['member', 'admin'])->default('member');

        $stmts = $this->g->compileCreate($bp);
        self::assertSame(
            'CREATE TABLE "users" ("role" TEXT NOT NULL DEFAULT \'member\')',
            $stmts[0],
        );
    }

    public function testTimestampsAndSoftDeletes(): void
    {
        $bp = new Blueprint('users');
        $bp->timestamps();
        $bp->softDeletes();

        $stmts = $this->g->compileCreate($bp);
        self::assertSame(
            'CREATE TABLE "users" ("created_at" TEXT, "updated_at" TEXT, "deleted_at" TEXT)',
            $stmts[0],
        );
    }

    public function testForeignKeyInlineAndCascadeOnDelete(): void
    {
        $bp = new Blueprint('posts');
        $bp->id();
        $bp->unsignedBigInt('user_id');
        $bp->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

        $stmts = $this->g->compileCreate($bp);
        self::assertSame(
            'CREATE TABLE "posts" ('
            . '"id" INTEGER PRIMARY KEY AUTOINCREMENT, '
            . '"user_id" INTEGER NOT NULL, '
            . 'FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE)',
            $stmts[0],
        );
    }

    public function testIndexAndUniqueIndexEmitSeparateStatements(): void
    {
        $bp = new Blueprint('users');
        $bp->id();
        $bp->string('email')->unique();           // inline UNIQUE
        $bp->index(['created_at']);               // named non-unique
        $bp->uniqueIndex(['team_id', 'role'], 'users_team_role_unique');

        $stmts = $this->g->compileCreate($bp);
        self::assertCount(3, $stmts);
        self::assertSame(
            'CREATE TABLE "users" ('
            . '"id" INTEGER PRIMARY KEY AUTOINCREMENT, '
            . '"email" TEXT NOT NULL UNIQUE)',
            $stmts[0],
        );
        self::assertSame(
            'CREATE INDEX "users_created_at_index" ON "users" ("created_at")',
            $stmts[1],
        );
        self::assertSame(
            'CREATE UNIQUE INDEX "users_team_role_unique" ON "users" ("team_id", "role")',
            $stmts[2],
        );
    }

    public function testMorphsAddsTypeIdAndCompositeIndex(): void
    {
        $bp = new Blueprint('media');
        $bp->id();
        $bp->morphs('attachable');

        $stmts = $this->g->compileCreate($bp);
        self::assertCount(2, $stmts);
        self::assertStringContainsString('"attachable_type" TEXT NOT NULL',  $stmts[0]);
        self::assertStringContainsString('"attachable_id" INTEGER NOT NULL', $stmts[0]);
        self::assertSame(
            'CREATE INDEX "attachable_index" ON "media" ("attachable_type", "attachable_id")',
            $stmts[1],
        );
    }

    public function testAlterAddDropAndRenameColumns(): void
    {
        $bp = new Blueprint('users');
        $bp->action = Blueprint::ACTION_ALTER;
        $bp->string('phone')->nullable();
        $bp->renameColumn('email', 'email_address');
        $bp->dropColumn('legacy');

        $stmts = $this->g->compileAlter($bp);
        self::assertSame([
            'ALTER TABLE "users" ADD COLUMN "phone" TEXT',
            'ALTER TABLE "users" RENAME COLUMN "email" TO "email_address"',
            'ALTER TABLE "users" DROP COLUMN "legacy"',
        ], $stmts);
    }

    public function testDropAndRename(): void
    {
        self::assertSame('DROP TABLE "users"',                       $this->g->compileDrop('users'));
        self::assertSame('DROP TABLE IF EXISTS "users"',             $this->g->compileDropIfExists('users'));
        self::assertSame('ALTER TABLE "users" RENAME TO "people"',   $this->g->compileRename('users', 'people'));
    }
}

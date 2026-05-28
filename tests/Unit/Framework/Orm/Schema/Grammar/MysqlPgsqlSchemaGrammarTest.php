<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Schema\Grammar;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Schema\Blueprint;
use Silver\Orm\Schema\Grammar\MysqlSchemaGrammar;
use Silver\Orm\Schema\Grammar\PgsqlSchemaGrammar;

final class MysqlPgsqlSchemaGrammarTest extends TestCase
{
    private function usersBlueprint(): Blueprint
    {
        $bp = new Blueprint('users');
        $bp->id();
        $bp->string('email', 191)->unique();
        $bp->string('name');
        $bp->json('preferences')->nullable();
        $bp->enum('role', ['member', 'admin'])->default('member');
        $bp->bool('active')->default(true);
        $bp->timestamps();
        $bp->softDeletes();
        return $bp;
    }

    public function testMysqlCreateUsesBigIntAutoIncrementAndInnoDb(): void
    {
        $g = new MysqlSchemaGrammar();
        $stmts = $g->compileCreate($this->usersBlueprint());

        // First statement = CREATE TABLE; remaining = follow-up indexes (none here, UNIQUE is inline).
        self::assertCount(1, $stmts);
        $sql = $stmts[0];

        self::assertStringContainsString('CREATE TABLE `users` (',           $sql);
        self::assertStringContainsString('`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', $sql);
        self::assertStringContainsString('`email` VARCHAR(191) NOT NULL UNIQUE', $sql);
        self::assertStringContainsString('`preferences` JSON',                $sql);
        self::assertStringContainsString("`role` ENUM('member', 'admin') NOT NULL DEFAULT 'member'", $sql);
        self::assertStringContainsString('`active` TINYINT(1) NOT NULL DEFAULT 1', $sql);
        self::assertStringContainsString('`created_at` TIMESTAMP',           $sql);
        self::assertStringContainsString('`deleted_at` TIMESTAMP',           $sql);
        self::assertStringContainsString('PRIMARY KEY (`id`)',               $sql);
        self::assertStringEndsWith('ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',  $sql);
    }

    public function testPgsqlCreateUsesBigSerialAndJsonb(): void
    {
        $bp = $this->usersBlueprint();
        // Promote one column to jsonb to confirm Pgsql distinguishes them.
        $bp->jsonb('document')->nullable();

        $g = new PgsqlSchemaGrammar();
        $stmts = $g->compileCreate($bp);
        $sql   = $stmts[0];

        self::assertStringContainsString('CREATE TABLE "users" (',         $sql);
        self::assertStringContainsString('"id" BIGSERIAL PRIMARY KEY',     $sql);
        self::assertStringContainsString('"email" VARCHAR(191) NOT NULL UNIQUE', $sql);
        self::assertStringContainsString('"preferences" JSON',             $sql);
        self::assertStringContainsString('"document" JSONB',               $sql);
        self::assertStringContainsString('"active" BOOLEAN NOT NULL DEFAULT 1', $sql);
        self::assertStringContainsString('"created_at" TIMESTAMP',         $sql);
        // Postgres enum is encoded as TEXT CHECK (...).
        self::assertStringContainsString('CHECK ("role" IN (\'member\', \'admin\'))', $sql);
    }

    public function testMysqlForeignKeyInline(): void
    {
        $bp = new Blueprint('posts');
        $bp->id();
        $bp->unsignedBigInt('user_id');
        $bp->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

        $g = new MysqlSchemaGrammar();
        $sql = $g->compileCreate($bp)[0];

        self::assertStringContainsString('FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', $sql);
    }

    public function testPgsqlForeignKeyInline(): void
    {
        $bp = new Blueprint('posts');
        $bp->id();
        $bp->unsignedBigInt('user_id');
        $bp->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

        $g = new PgsqlSchemaGrammar();
        $sql = $g->compileCreate($bp)[0];

        self::assertStringContainsString('FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE', $sql);
    }
}

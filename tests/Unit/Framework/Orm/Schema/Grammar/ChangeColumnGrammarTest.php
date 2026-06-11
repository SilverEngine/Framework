<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Schema\Grammar;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Schema\Blueprint;
use Silver\Orm\Schema\Grammar\MysqlSchemaGrammar;
use Silver\Orm\Schema\Grammar\PgsqlSchemaGrammar;

final class ChangeColumnGrammarTest extends TestCase
{
    public function testMysqlChangeCompilesToModifyColumn(): void
    {
        $bp = new Blueprint('users');
        $bp->action = Blueprint::ACTION_ALTER;
        $bp->string('name', 100)->nullable()->change();

        $stmts = new MysqlSchemaGrammar()->compileAlter($bp);

        self::assertSame(
            ['ALTER TABLE `users` MODIFY COLUMN `name` VARCHAR(100)'],
            $stmts,
        );
    }

    public function testMysqlChangeWithDefaultAndNotNull(): void
    {
        $bp = new Blueprint('users');
        $bp->action = Blueprint::ACTION_ALTER;
        $bp->string('role', 50)->default('member')->change();

        $stmts = new MysqlSchemaGrammar()->compileAlter($bp);

        self::assertSame(
            ["ALTER TABLE `users` MODIFY COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'member'"],
            $stmts,
        );
    }

    public function testPgsqlChangeEmitsTypeNullabilityAndDefault(): void
    {
        $bp = new Blueprint('users');
        $bp->action = Blueprint::ACTION_ALTER;
        $bp->string('name', 100)->nullable()->change();

        $stmts = new PgsqlSchemaGrammar()->compileAlter($bp);

        self::assertSame(
            [
                'ALTER TABLE "users" ALTER COLUMN "name" TYPE VARCHAR(100)',
                'ALTER TABLE "users" ALTER COLUMN "name" DROP NOT NULL',
                'ALTER TABLE "users" ALTER COLUMN "name" DROP DEFAULT',
            ],
            $stmts,
        );
    }

    public function testPgsqlChangeSetsNotNullAndDefault(): void
    {
        $bp = new Blueprint('users');
        $bp->action = Blueprint::ACTION_ALTER;
        $bp->string('role', 50)->default('member')->change();

        $stmts = new PgsqlSchemaGrammar()->compileAlter($bp);

        self::assertSame(
            [
                'ALTER TABLE "users" ALTER COLUMN "role" TYPE VARCHAR(50)',
                'ALTER TABLE "users" ALTER COLUMN "role" SET NOT NULL',
                'ALTER TABLE "users" ALTER COLUMN "role" SET DEFAULT \'member\'',
            ],
            $stmts,
        );
    }

    public function testChangedColumnsDoNotLeakIntoAddColumn(): void
    {
        $bp = new Blueprint('users');
        $bp->action = Blueprint::ACTION_ALTER;
        $bp->string('nick', 50);                       // genuine ADD
        $bp->string('name', 100)->nullable()->change(); // CHANGE

        $stmts = new MysqlSchemaGrammar()->compileAlter($bp);

        self::assertSame(
            [
                'ALTER TABLE `users` ADD COLUMN `nick` VARCHAR(50) NOT NULL',
                'ALTER TABLE `users` MODIFY COLUMN `name` VARCHAR(100)',
            ],
            $stmts,
        );
    }
}

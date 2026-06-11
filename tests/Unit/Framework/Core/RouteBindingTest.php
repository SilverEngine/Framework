<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Core;

use PHPUnit\Framework\TestCase;
use Silver\Core\DI;
use Silver\Exception\NotFoundException;
use Silver\Orm\Attributes\PrimaryKey;
use Silver\Orm\Attributes\Table;
use Silver\Orm\Cache\ModelCache;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Model\AttributeRegistry;
use Silver\Orm\Model\EventDispatcher;
use Silver\Orm\Model\Model;

#[Table('di_users')]
final class DiUser extends Model
{
    protected static array $fillable = ['name'];

    #[PrimaryKey]
    public ?int $id = null;

    public string $name = '';
}

/**
 * F1 — route param injection + model binding. Route variables arrive
 * in DI::call() as name-keyed strings; typed controller params must
 * receive coerced scalars, and Model-typed params must resolve via
 * find() (404 on miss).
 */
final class RouteBindingTest extends TestCase
{
    private ConnectionManager $cm;

    protected function setUp(): void
    {
        AttributeRegistry::flush();
        ModelCache::flushAll();
        DiUser::unboot();

        $this->cm = new ConnectionManager();
        $this->cm->connect('default', 'sqlite::memory:');
        $this->cm->setDefault('default');
        $this->cm->exec('CREATE TABLE di_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');

        Model::bind($this->cm, new EventDispatcher());
    }

    // ---------- scalar coercion ----------

    public function testIntParamCoercedFromRouteString(): void
    {
        $out = app(DI::class)->call(
            fn (int $id): int => $id,
            ['id' => '5'],
        );
        self::assertSame(5, $out);
    }

    public function testFloatParamCoercedFromRouteString(): void
    {
        $out = app(DI::class)->call(
            fn (float $ratio): float => $ratio,
            ['ratio' => '2.5'],
        );
        self::assertSame(2.5, $out);
    }

    public function testBoolParamCoercedFromRouteString(): void
    {
        $out = app(DI::class)->call(
            fn (bool $active): bool => $active,
            ['active' => '1'],
        );
        self::assertTrue($out);
    }

    public function testStringParamStaysUntouched(): void
    {
        $out = app(DI::class)->call(
            fn (string $slug): string => $slug,
            ['slug' => '42'],
        );
        self::assertSame('42', $out);
    }

    // ---------- model binding ----------

    public function testModelParamResolvedByRouteVariable(): void
    {
        $u = DiUser::create(['name' => 'Alice']);

        $out = app(DI::class)->call(
            fn (DiUser $user): string => $user->name,
            ['user' => (string) $u->id],
        );
        self::assertSame('Alice', $out);
    }

    public function testModelBindingMissThrowsNotFound(): void
    {
        $this->expectException(NotFoundException::class);

        app(DI::class)->call(
            fn (DiUser $user): string => $user->name,
            ['user' => '999'],
        );
    }

    public function testNullableModelParamMissGivesNull(): void
    {
        $out = app(DI::class)->call(
            fn (?DiUser $user): string => $user?->name ?? 'none',
            ['user' => '999'],
        );
        self::assertSame('none', $out);
    }

    public function testFqnKeyedInstanceWinsOverNameBinding(): void
    {
        $instance = new DiUser();
        $instance->name = 'preregistered';

        $out = app(DI::class)->call(
            fn (DiUser $user): string => $user->name,
            [DiUser::class => $instance, 'user' => '999'],
        );
        self::assertSame('preregistered', $out);
    }
}

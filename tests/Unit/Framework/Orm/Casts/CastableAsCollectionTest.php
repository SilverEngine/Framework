<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Casts;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Cache\ModelCache;
use Silver\Orm\Casts\CastResolver;
use Silver\Orm\Collection;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Model\AttributeRegistry;
use Silver\Orm\Model\EventDispatcher;
use Silver\Orm\Model\Model;
use Tests\Unit\Framework\Orm\Casts\fixtures\Money;
use Tests\Unit\Framework\Orm\Casts\fixtures\Product;

final class CastableAsCollectionTest extends TestCase
{
    private ConnectionManager $cm;

    protected function setUp(): void
    {
        AttributeRegistry::flush();
        ModelCache::flushAll();
        CastResolver::flush();
        Product::unboot();

        $this->cm = new ConnectionManager();
        $this->cm->connect('default', 'sqlite::memory:');
        $this->cm->setDefault('default');
        $this->cm->exec(
            'CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, price TEXT, variants TEXT)',
        );

        Model::bind($this->cm, new EventDispatcher());
    }

    // ---------- Castable domain object ----------

    public function testCastableValueObjectRoundTrips(): void
    {
        $p = Product::create(['name' => 'Mug']);
        $p->price = new Money('EUR', 1250);
        $p->save();

        $rawPrice = $this->cm->raw('SELECT price FROM products WHERE id = ?', [$p->id])->fetchColumn();
        self::assertSame('EUR 12.50', $rawPrice);

        ModelCache::flushAll();
        $found = Product::find($p->id);
        self::assertInstanceOf(Money::class, $found->price);
        self::assertSame('EUR', $found->price->currency);
        self::assertSame(1250, $found->price->cents);
    }

    public function testCastableNullStaysNull(): void
    {
        $p = Product::create(['name' => 'Sticker']);

        ModelCache::flushAll();
        self::assertNull(Product::find($p->id)->price);
    }

    // ---------- AsCollection ----------

    public function testAsCollectionHydratesJsonColumnToCollection(): void
    {
        $this->cm->raw(
            'INSERT INTO products (name, variants) VALUES (?, ?)',
            ['Shirt', '[{"size":"M"},{"size":"L"}]'],
        );

        $p = Product::query()->where('name', 'Shirt')->first();

        self::assertInstanceOf(Collection::class, $p->variants);
        self::assertCount(2, $p->variants);
        self::assertSame('M', $p->variants[0]['size']);
    }

    public function testAsCollectionPersistsAsJson(): void
    {
        $p = Product::create(['name' => 'Cap']);
        $p->variants = new Collection([['size' => 'S']]);
        $p->save();

        $raw = $this->cm->raw('SELECT variants FROM products WHERE id = ?', [$p->id])->fetchColumn();
        self::assertSame('[{"size":"S"}]', $raw);
    }

    public function testAsCollectionNullStaysNull(): void
    {
        $p = Product::create(['name' => 'Pin']);

        ModelCache::flushAll();
        self::assertNull(Product::find($p->id)->variants);
    }
}

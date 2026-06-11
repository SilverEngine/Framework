<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Casts\fixtures;

use Silver\Orm\Attributes\Cast;
use Silver\Orm\Attributes\PrimaryKey;
use Silver\Orm\Attributes\Table;
use Silver\Orm\Casts\AsCollection;
use Silver\Orm\Collection;
use Silver\Orm\Model\Model;

#[Table('products')]
final class Product extends Model
{
    protected static array $fillable = ['name', 'price', 'variants'];

    #[PrimaryKey]
    public ?int $id = null;

    public string $name = '';

    #[Cast(Money::class)]
    public ?Money $price = null;

    #[Cast(AsCollection::class)]
    public ?Collection $variants = null;
}

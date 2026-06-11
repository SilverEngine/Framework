<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Relations\fixtures;

use Silver\Orm\Attributes\PrimaryKey;
use Silver\Orm\Attributes\Table;
use Silver\Orm\Model\Model;
use Silver\Orm\Relations\Morph\MorphToMany;

#[Table('posts')]
final class Post extends Model
{
    protected static array $fillable = ['title'];

    #[PrimaryKey]
    public ?int $id = null;

    public string $title = '';

    public function labels(): MorphToMany
    {
        return $this->morphToMany(Label::class, 'labelable');
    }
}

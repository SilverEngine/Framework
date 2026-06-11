<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Relations\fixtures;

use Silver\Orm\Attributes\PrimaryKey;
use Silver\Orm\Attributes\Table;
use Silver\Orm\Model\Model;
use Silver\Orm\Relations\Morph\MorphToMany;

#[Table('labels')]
final class Label extends Model
{
    protected static array $fillable = ['name'];

    #[PrimaryKey]
    public ?int $id = null;

    public string $name = '';

    public function posts(): MorphToMany
    {
        return $this->morphedByMany(Post::class, 'labelable');
    }

    public function videos(): MorphToMany
    {
        return $this->morphedByMany(Video::class, 'labelable');
    }
}

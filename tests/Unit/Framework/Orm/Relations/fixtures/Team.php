<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Relations\fixtures;

use Silver\Orm\Attributes\PrimaryKey;
use Silver\Orm\Attributes\Table;
use Silver\Orm\Model\Model;
use Silver\Orm\Relations\HasMany;

#[Table('teams')]
final class Team extends Model
{
    protected static array $fillable = ['name'];

    #[PrimaryKey]
    public ?int $id = null;

    public string $name = '';

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}

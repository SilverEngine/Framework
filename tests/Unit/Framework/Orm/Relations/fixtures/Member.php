<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Relations\fixtures;

use Silver\Orm\Attributes\PrimaryKey;
use Silver\Orm\Attributes\Table;
use Silver\Orm\Model\Model;
use Silver\Orm\Relations\BelongsTo;
use Silver\Orm\Relations\BelongsToMany;
use Silver\Orm\Relations\HasOne;

#[Table('members')]
final class Member extends Model
{
    protected static array $fillable = ['name', 'team_id'];

    #[PrimaryKey]
    public ?int $id = null;

    public string $name    = '';
    public ?int   $team_id = null;

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'member_tag');
    }
}

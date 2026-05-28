<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Relations\fixtures;

use Silver\Orm\Attributes\PrimaryKey;
use Silver\Orm\Attributes\Table;
use Silver\Orm\Model\Model;

#[Table('profiles')]
final class Profile extends Model
{
    protected static array $fillable = ['member_id', 'bio'];

    #[PrimaryKey]
    public ?int $id = null;

    public ?int   $member_id = null;
    public string $bio       = '';
}

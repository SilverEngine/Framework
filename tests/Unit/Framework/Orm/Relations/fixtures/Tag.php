<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Relations\fixtures;

use Silver\Orm\Attributes\PrimaryKey;
use Silver\Orm\Attributes\Table;
use Silver\Orm\Model\Model;

#[Table('tags')]
final class Tag extends Model
{
    protected static array $fillable = ['label'];

    #[PrimaryKey]
    public ?int $id = null;

    public string $label = '';
}

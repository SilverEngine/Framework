<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Model\fixtures;

use Silver\Orm\Attributes\PrimaryKey;
use Silver\Orm\Attributes\Table;
use Silver\Orm\Model\Model;

/**
 * Fixture exercising set{Studly}Attribute() mutators — maps onto the
 * same `users` table as the User fixture.
 */
#[Table('users')]
final class MutatedUser extends Model
{
    protected static array $fillable = ['email', 'name'];
    protected static array $appends  = ['shout_name'];

    #[PrimaryKey]
    public ?int $id = null;

    public string $email = '';
    public string $name  = '';

    public function setNameAttribute(string $value): string
    {
        return ucwords(trim($value));
    }

    public function getShoutNameAttribute(): string
    {
        return strtoupper($this->name);
    }
}

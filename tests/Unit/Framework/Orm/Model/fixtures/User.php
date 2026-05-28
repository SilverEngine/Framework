<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Model\fixtures;

use DateTimeImmutable;
use Silver\Orm\Attributes\Cast;
use Silver\Orm\Attributes\Hidden;
use Silver\Orm\Attributes\PrimaryKey;
use Silver\Orm\Attributes\Table;
use Silver\Orm\Attributes\UseRepository;
use Silver\Orm\Concerns\SoftDeletes;
use Silver\Orm\Concerns\Timestamps;
use Silver\Orm\Concerns\ObservedBy;
use Silver\Orm\Model\Model;

#[Table('users')]
#[Timestamps]
#[SoftDeletes]
#[ObservedBy(UserObserver::class)]
#[UseRepository(UserRepository::class)]
final class User extends Model
{
    // Array-form alternatives to per-property attributes — both work
    // and union together. This style stays close to the legacy
    // Eloquent shape that callers are used to.
    protected static array $fillable   = ['email', 'name', 'preferences'];
    protected static array $editable   = ['email', 'name', 'preferences'];
    protected static array $hidden     = ['password'];
    protected static array $searchable = ['email', 'name'];

    #[PrimaryKey]
    public ?int $id = null;

    public string $email;
    public string $name;

    #[Hidden]
    public string $password = '';

    #[Cast(UserRole::class)]
    public UserRole $role = UserRole::Member;

    #[Cast('array')]
    public array $preferences = [];

    public ?DateTimeImmutable $created_at = null;
    public ?DateTimeImmutable $updated_at = null;
    public ?DateTimeImmutable $deleted_at = null;

    public static int $bootCount = 0;

    protected static function boot(): void
    {
        self::$bootCount++;
    }
}

<?php
declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use Silver\Auth\Contracts\Authenticatable;
use Silver\Orm\Attributes\Hidden;
use Silver\Orm\Attributes\PrimaryKey;
use Silver\Orm\Attributes\Table;
use Silver\Orm\Concerns\SoftDeletes;
use Silver\Orm\Concerns\Timestamps;
use Silver\Orm\Model\Model;

#[Table('users')]
#[Timestamps]
#[SoftDeletes]
final class Users extends Model implements Authenticatable
{
    protected static array $fillable   = ['username', 'email', 'active'];
    protected static array $searchable = ['username', 'email'];

    #[PrimaryKey]
    public ?int $id = null;

    public string $username = '';
    public string $email    = '';

    #[Hidden]
    public string $password = '';

    #[Hidden]
    public string $salt = '';

    public bool $active = true;

    public ?DateTimeImmutable $created_at = null;
    public ?DateTimeImmutable $updated_at = null;
    public ?DateTimeImmutable $deleted_at = null;

    public function getAuthIdentifier(): int|string
    {
        return $this->id ?? 0;
    }

    public function getAuthPasswordHash(): string
    {
        return $this->password;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }
}

<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Auth\fixtures;

use Silver\Auth\Contracts\Authenticatable;
use Silver\Orm\Attributes\PrimaryKey;
use Silver\Orm\Attributes\Table;
use Silver\Orm\Model\Model;

#[Table('auth_users')]
final class AuthUser extends Model implements Authenticatable
{
    protected static array $fillable = ['email', 'password'];

    #[PrimaryKey]
    public ?int $id = null;

    public string $email = '';
    public string $password = '';

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

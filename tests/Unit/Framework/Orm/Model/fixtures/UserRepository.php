<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Model\fixtures;

use Silver\Orm\Model\Repository;

/**
 * @extends Repository<User>
 */
final class UserRepository extends Repository
{
    public function findByEmail(string $email): ?User
    {
        return $this->query()->where('email', $email)->first();
    }

    public function admins(): array
    {
        return $this->query()->where('role', 'admin')->all();
    }
}

<?php
declare(strict_types=1);

namespace Silver\Orm\Model;

use Silver\Orm\Model\ModelBuilder;

/**
 * Marker base class for repositories. A repository hangs custom
 * query / persistence methods off a model — keeping the model
 * itself thin and putting reusable lookups in one place.
 *
 *   final class UserRepository extends Repository
 *   {
 *       public function findByEmail(string $email): ?User
 *       {
 *           return $this->query()->where('email', $email)->first();
 *       }
 *
 *       public function activeAdmins(): array
 *       {
 *           return $this->query()->where('role', 'admin')->whereNull('banned_at')->all();
 *       }
 *   }
 *
 * Registration:
 *
 *   #[Repository(UserRepository::class)]                  // attribute
 *   final class User extends Model {}
 *
 *   // or programmatically:
 *   User::useRepository(UserRepository::class);
 *
 * Once registered, missing static calls on the model are routed to
 * the repository:
 *
 *   User::findByEmail('a@b')          // dispatched to UserRepository::findByEmail()
 *   User::activeAdmins()              // dispatched to UserRepository::activeAdmins()
 *
 * The repository instance is resolved through the IoC container if
 * available (constructor injection works), else `new` is used.
 *
 * @template T of Model
 */
abstract class Repository
{
    /** @var class-string<T> */
    private string $modelClass;

    /** @internal called by Model::useRepository to bind the model. */
    public function bindModel(string $modelClass): void
    {
        $this->modelClass = $modelClass;
    }

    /**
     * @return class-string<T>
     */
    public function modelClass(): string
    {
        return $this->modelClass;
    }

    /**
     * Fresh query targeting the bound model. Has the model's
     * global scopes (soft delete, etc.) already applied — same as
     * calling Model::query() directly.
     */
    protected function query(): ModelBuilder
    {
        /** @var class-string<Model> $cls */
        $cls = $this->modelClass;
        return $cls::query();
    }
}

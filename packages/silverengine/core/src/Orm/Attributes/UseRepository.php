<?php
declare(strict_types=1);

namespace Silver\Orm\Attributes;

use Silver\Orm\Model\Repository;
use Attribute;

/**
 * Cleaner-named alias of {@see RepositoryAttribute}. Prefer this
 * spelling at the call site to avoid confusion with the
 * Repository base class.
 *
 *   #[UseRepository(UserRepository::class)]
 *   final class User extends Model {}
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class UseRepository
{
    /**
     * @param class-string<Repository> $class
     */
    public function __construct(public string $class) {}
}

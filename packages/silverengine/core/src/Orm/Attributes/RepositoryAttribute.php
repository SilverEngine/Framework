<?php
declare(strict_types=1);

namespace Silver\Orm\Attributes;

use Silver\Orm\Model\Repository;
use Attribute;

/**
 * Bind a repository class to a model. The repository receives
 * forwarded calls for any method that isn't defined on the model.
 *
 *   #[Repository(UserRepository::class)]
 *   final class User extends \Silver\Orm\Model\Model {}
 *
 * (The class is named RepositoryAttribute to avoid colliding with
 * the {@see \Silver\Orm\Model\Repository} base class — but it is
 * aliased to `Repository` for the attribute-call syntax. Use either
 * the alias or the FQN; the registry handles both.)
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class RepositoryAttribute
{
    /**
     * @param class-string<Repository> $class
     */
    public function __construct(public string $class) {}
}

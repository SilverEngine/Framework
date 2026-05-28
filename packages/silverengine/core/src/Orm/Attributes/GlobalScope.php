<?php
declare(strict_types=1);

namespace Silver\Orm\Attributes;

use Attribute;

/**
 * Apply a scope to every query on this model. The named class must
 * implement {@see \Silver\Orm\Contracts\GlobalScopeInterface}. Disable
 * per-query via `->withoutGlobalScope(SomeScope::class)`.
 *
 *   #[GlobalScope(PublishedScope::class)]
 *   final class Post extends Model {}
 *
 * Repeatable: tag the model with more than one to layer scopes.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class GlobalScope
{
    /** @param class-string<\Silver\Orm\Contracts\GlobalScopeInterface> $scope */
    public function __construct(
        public string $scope,
    ) {}
}

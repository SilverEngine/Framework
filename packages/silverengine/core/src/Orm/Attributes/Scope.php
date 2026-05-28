<?php
declare(strict_types=1);

namespace Silver\Orm\Attributes;

use Attribute;

/**
 * Tag a model method as a query scope. Once tagged, calling the
 * method via the Builder forwards into the model:
 *
 *   #[Scope]
 *   public function active(\Silver\Orm\Query\Builder $q): \Silver\Orm\Query\Builder
 *   {
 *       return $q->whereNull('banned_at');
 *   }
 *
 *   User::query()->active()->all();
 *
 * Methods without #[Scope] are NOT auto-discovered — no implicit
 * "method-as-scope" magic. This keeps the Builder's public surface
 * predictable and reflection-cheap (the registry walks once per class).
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Scope
{
}

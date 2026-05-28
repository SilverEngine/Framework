<?php
declare(strict_types=1);

namespace Silver\Orm\Contracts;

use Silver\Orm\Query\Builder;

/**
 * A global scope is applied to every Model::query() call on a model
 * tagged with #[GlobalScope(MyScope::class)]. Implementations should
 * be stateless — the same instance may be reused across queries.
 */
interface GlobalScopeInterface
{
    public function apply(Builder $query): void;
}

<?php
declare(strict_types=1);

namespace Silver\Orm\Model;

use Silver\Orm\Contracts\GlobalScopeInterface;
use Closure;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Query\Builder;
use Silver\Orm\Query\Compiler;
use Silver\Orm\Query\Node\Expression;
use Silver\Orm\Query\Node\Raw;
use Silver\Orm\Query\Node\Subquery;
use Silver\Orm\Query\QueryState;
use Silver\Orm\Relations\Relation;

/**
 * Builder subclass that knows about a Model class — terminals return
 * hydrated instances instead of plain assoc arrays.
 *
 * @template T of Model
 */
final class ModelBuilder extends Builder
{
    /** @var array<string, ?Closure(Builder): void> Relation name → optional constraint. */
    private array $with = [];

    /** @var list<class-string<GlobalScopeInterface>> */
    private array $pendingGlobalScopes = [];

    /** @var array<class-string<GlobalScopeInterface>, true> */
    private array $disabledGlobalScopes = [];

    private bool $globalScopesApplied = false;

    /** Soft-delete column when the model opted in via #[SoftDeletes]. */
    private ?string $softDeleteColumn = null;

    /** @var 'exclude'|'with'|'only' */
    private string $trashedMode = 'exclude';

    private bool $softDeleteApplied = false;

    /** @param class-string<T> $modelClass */
    public function __construct(
        ConnectionManager $connections,
        Compiler          $compiler,
        ?QueryState       $state,
        private readonly string $modelClass,
    ) {
        parent::__construct($connections, $compiler, $state);
    }

    /** @param class-string<GlobalScopeInterface> $scopeClass */
    public function registerGlobalScope(string $scopeClass): static
    {
        $this->pendingGlobalScopes[] = $scopeClass;
        return $this;
    }

    /** @param class-string<GlobalScopeInterface> $scopeClass */
    public function withoutGlobalScope(string $scopeClass): static
    {
        $this->disabledGlobalScopes[$scopeClass] = true;
        return $this;
    }

    public function withoutGlobalScopes(): static
    {
        foreach ($this->pendingGlobalScopes as $cls) {
            $this->disabledGlobalScopes[$cls] = true;
        }
        return $this;
    }

    private function applyGlobalScopes(): void
    {
        if ($this->globalScopesApplied) {
            return;
        }
        $this->globalScopesApplied = true;
        foreach ($this->pendingGlobalScopes as $cls) {
            if (isset($this->disabledGlobalScopes[$cls])) {
                continue;
            }
            $scope = function_exists('app') ? app($cls) : new $cls();
            $scope->apply($this);
        }
    }

    // ---- soft deletes -------------------------------------------------

    /** Registered by Model::query() when the model has #[SoftDeletes]. */
    public function softDeletes(string $column): static
    {
        $this->softDeleteColumn = $column;
        return $this;
    }

    /** Include trashed rows in the results. */
    public function withTrashed(): static
    {
        $this->trashedMode = 'with';
        return $this;
    }

    /** Only trashed rows. */
    public function onlyTrashed(): static
    {
        $this->trashedMode = 'only';
        return $this;
    }

    private function applySoftDeleteScope(): void
    {
        if ($this->softDeleteColumn === null || $this->softDeleteApplied) {
            return;
        }
        $this->softDeleteApplied = true;
        match ($this->trashedMode) {
            'exclude' => $this->whereNull($this->softDeleteColumn),
            'only'    => $this->whereNotNull($this->softDeleteColumn),
            'with'    => null,
        };
    }

    /**
     * Apply every deferred scope (global scopes + soft-delete filter)
     * exactly once, right before SQL is built. Hooked into ALL
     * terminals via the toSqlAndBindings()/update()/delete()
     * overrides, so count(), aggregates, paginate() totals and bulk
     * writes are scoped the same as get().
     */
    private function applyQueryScopes(): void
    {
        $this->applyGlobalScopes();
        $this->applySoftDeleteScope();
    }

    /**
     * Apply deferred scopes now and expose the state for embedding as
     * a subquery (the outer grammar compiles QueryState directly, so
     * the sub-builder's lazy scope application would otherwise be
     * skipped).
     */
    public function subqueryState(): QueryState
    {
        $this->applyQueryScopes();
        return $this->state;
    }

    #[\Override]
    public function toSqlAndBindings(): array
    {
        $this->applyQueryScopes();
        return parent::toSqlAndBindings();
    }

    #[\Override]
    public function update(array $values): int
    {
        $this->applyQueryScopes();
        return parent::update($values);
    }

    #[\Override]
    public function delete(): int
    {
        $this->applyQueryScopes();
        return parent::delete();
    }

    /**
     * Dispatch unknown method calls into the model's #[Scope] methods.
     * `User::query()->active()->all()` lands on
     * `User::active(Builder $q)` if it's tagged.
     */
    public function __call(string $method, array $args): mixed
    {
        $meta = AttributeRegistry::for($this->modelClass);
        if (isset($meta->scopes[$method])) {
            $instance = new $this->modelClass();
            $result   = $instance->{$method}($this, ...$args);
            return $result ?? $this;
        }
        throw new \BadMethodCallException(
            sprintf('Method %s::%s does not exist (and no #[Scope]-tagged method on %s matches).', self::class, $method, $this->modelClass),
        );
    }

    // ---- relation existence -------------------------------------------

    /**
     * Filter to parents having at least one related row:
     *
     *     User::query()->whereHas('posts')
     *     User::query()->whereHas('posts', fn ($q) => $q->where('published', true))
     *
     * Compiles to a correlated EXISTS subquery. The related model's
     * own scopes (soft deletes, global scopes) apply inside it.
     */
    public function whereHas(string $relation, ?Closure $constraint = null): static
    {
        $this->state->wheres[] = new Expression('EXISTS', [
            new Subquery($this->relationSubquery($relation, $constraint)),
        ]);
        return $this;
    }

    /** Inverse of {@see whereHas()} — parents with NO related rows. */
    public function whereDoesntHave(string $relation, ?Closure $constraint = null): static
    {
        $this->state->wheres[] = new Expression('NOT EXISTS', [
            new Subquery($this->relationSubquery($relation, $constraint)),
        ]);
        return $this;
    }

    /**
     * Project a correlated `COUNT(*)` per relation as
     * `{relation}_count`. Lands in the model's extras bag, readable as
     * `$user->posts_count`.
     */
    public function withCount(string ...$relations): static
    {
        foreach ($relations as $relation) {
            $sub = $this->relationSubquery($relation, null);
            $sub->select = [new Raw('COUNT(*)')];

            // Keep the base columns: an empty select would otherwise
            // stop meaning "*" once the count column is added.
            if ($this->state->select === []) {
                /** @var class-string<Model> $model */
                $model = $this->modelClass;
                $this->select([$model::table() . '.*']);
            }
            $this->state->select[] = new Subquery($sub, $relation . '_count');
        }
        return $this;
    }

    private function relationSubquery(string $relation, ?Closure $constraint): QueryState
    {
        $instance = new ($this->modelClass)();
        if (!method_exists($instance, $relation)) {
            throw new \BadMethodCallException(
                sprintf('Relation %s::%s() does not exist.', $this->modelClass, $relation),
            );
        }

        $rel = $instance->{$relation}();
        if (!$rel instanceof Relation) {
            throw new \LogicException(
                sprintf('%s::%s() does not return a Relation.', $this->modelClass, $relation),
            );
        }

        $sub = $rel->existenceQuery();
        if ($constraint !== null) {
            $constraint($sub);
        }
        return $sub->subqueryState();
    }

    /**
     * Mark relations for eager loading. Accepts:
     *
     *   ->with('posts')
     *   ->with('posts', 'team')
     *   ->with(['posts.comments', 'team'])
     *   ->with(['posts' => fn (Builder $q) => $q->where('published', true)])
     *
     * @param array<int|string, string|Closure>|string $relations
     */
    public function with(array|string ...$relations): static
    {
        $merged = [];
        foreach ($relations as $r) {
            if (is_string($r)) {
                $merged[$r] = null;
            } else {
                foreach ($r as $k => $v) {
                    if (is_int($k)) {
                        $merged[(string) $v] = null;
                    } else {
                        $merged[$k] = $v;
                    }
                }
            }
        }
        foreach ($merged as $name => $cb) {
            $this->with[$name] = $cb;
        }
        return $this;
    }

    /** @return list<T> */
    #[\Override]
    public function get(): array
    {
        $this->applyQueryScopes();

        $rows = parent::get();
        $out  = [];
        foreach ($rows as $row) {
            /** @var T $instance */
            $instance = Hydrator::hydrate($this->modelClass, $row);
            $out[]    = $instance;
        }
        if ($this->with !== [] && $out !== []) {
            $this->loadRelations($out);
        }
        return $out;
    }

    /** @param list<T> $models */
    private function loadRelations(array $models): void
    {
        // Group dotted paths by their top-level segment.
        $tree = [];
        foreach ($this->with as $path => $cb) {
            $segments = explode('.', $path, 2);
            $head     = $segments[0];
            $tail     = $segments[1] ?? null;
            $tree[$head] ??= ['cb' => null, 'children' => [], 'tailCbs' => []];
            // Constraint closure applies only when the path ends here.
            if ($tail === null) {
                $tree[$head]['cb'] = $cb;
            } else {
                $tree[$head]['children'][$tail] = $cb;
            }
        }

        foreach ($tree as $name => $spec) {
            if (!method_exists($models[0], $name)) {
                continue;
            }
            /** @var Relation $relation */
            $relation = $models[0]->{$name}();
            $query    = $relation->query();
            if ($spec['cb'] !== null) {
                ($spec['cb'])($query);
            }
            // Plug the constrained builder back into the relation by
            // re-running eager on the same builder shape. Simplest:
            // let the relation eager-load (uses its own query), then
            // apply children recursively on the results.
            $results = $relation->eager($models);
            $relation->match($models, $results, $name);

            if ($spec['children'] !== [] && $results !== []) {
                // Recurse with a fresh ModelBuilder bound to the related model.
                $relatedClass = $results[0]::class;
                $nested = (new self($this->connections, $this->compiler, null, $relatedClass));
                $nested->with($spec['children']);
                $nested->with = $spec['children'];
                $nested->loadRelations($results);
            }
        }
    }

    /** @return list<T> */
    #[\Override]
    public function all(): array { return $this->get(); }

    /** @return T|null */
    #[\Override]
    public function first(): mixed
    {
        $clone = clone $this;
        $rows  = $clone->limit(1)->get();
        return $rows[0] ?? null;
    }

    /** @return T|null */
    #[\Override]
    public function find(mixed $id, string $pk = 'id'): mixed
    {
        return (clone $this)->where($pk, $id)->first();
    }

    #[\Override]
    public function newQuery(): static
    {
        return new self($this->connections, $this->compiler, null, $this->modelClass);
    }
}

<?php
declare(strict_types=1);

namespace Silver\Orm\Model;

use Closure;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Query\Builder;
use Silver\Orm\Query\Compiler;
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

    /** @var list<class-string<\Silver\Orm\Contracts\GlobalScopeInterface>> */
    private array $pendingGlobalScopes = [];

    /** @var array<class-string<\Silver\Orm\Contracts\GlobalScopeInterface>, true> */
    private array $disabledGlobalScopes = [];

    private bool $globalScopesApplied = false;

    /** @param class-string<T> $modelClass */
    public function __construct(
        ConnectionManager $connections,
        Compiler          $compiler,
        ?QueryState       $state,
        private readonly string $modelClass,
    ) {
        parent::__construct($connections, $compiler, $state);
    }

    /** @param class-string<\Silver\Orm\Contracts\GlobalScopeInterface> $scopeClass */
    public function registerGlobalScope(string $scopeClass): static
    {
        $this->pendingGlobalScopes[] = $scopeClass;
        return $this;
    }

    /** @param class-string<\Silver\Orm\Contracts\GlobalScopeInterface> $scopeClass */
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

    /**
     * Dispatch unknown method calls into the model's #[Scope] methods.
     * `User::query()->active()->all()` lands on
     * `User::active(Builder $q)` if it's tagged.
     */
    public function __call(string $method, array $args): mixed
    {
        $meta = \Silver\Orm\Model\AttributeRegistry::for($this->modelClass);
        if (isset($meta->scopes[$method])) {
            $instance = new $this->modelClass();
            $result   = $instance->{$method}($this, ...$args);
            return $result ?? $this;
        }
        throw new \BadMethodCallException(
            sprintf('Method %s::%s does not exist (and no #[Scope]-tagged method on %s matches).', static::class, $method, $this->modelClass),
        );
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
    public function get(): array
    {
        $this->applyGlobalScopes();

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
    public function all(): array { return $this->get(); }

    /** @return T|null */
    public function first(): mixed
    {
        $clone = clone $this;
        $rows  = $clone->limit(1)->get();
        return $rows[0] ?? null;
    }

    /** @return T|null */
    public function find(mixed $id, string $pk = 'id'): mixed
    {
        return (clone $this)->where($pk, $id)->first();
    }

    public function newQuery(): static
    {
        return new self($this->connections, $this->compiler, null, $this->modelClass);
    }
}

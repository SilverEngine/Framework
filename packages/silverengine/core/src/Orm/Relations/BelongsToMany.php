<?php
declare(strict_types=1);

namespace Silver\Orm\Relations;

use Silver\Orm\Collection;
use Silver\Orm\Model\Model;
use Silver\Orm\Query\Builder;

/**
 * Many-to-many via a pivot table. user->roles: user_role pivot has
 * user_id + role_id, accessible as $role->pivot.
 *
 * @template TParent  of Model
 * @template TRelated of Model
 * @extends Relation<TParent, TRelated>
 */
final class BelongsToMany extends Relation
{
    /** @var list<string> Extra pivot columns to project onto the result models. */
    private array $pivotColumns = [];

    public function __construct(
        Model $parent,
        string $relatedClass,
        protected readonly string $pivotTable,
        protected readonly string $foreignPivotKey,   // user_id
        protected readonly string $relatedPivotKey,   // role_id
        protected readonly string $parentKey,         // users.id
        protected readonly string $relatedKey,        // roles.id
    ) {
        parent::__construct($parent, $relatedClass);
    }

    public function withPivot(string ...$columns): self
    {
        foreach ($columns as $c) { $this->pivotColumns[] = $c; }
        return $this;
    }

    public function attach(mixed ...$ids): int
    {
        $rows = [];
        foreach ($ids as $id) {
            $rows[] = [
                $this->foreignPivotKey => $this->parent->{$this->parentKey},
                $this->relatedPivotKey => $id,
            ];
        }
        return $rows === [] ? 0 : $this->pivot()->insert($rows);
    }

    public function detach(mixed ...$ids): int
    {
        $b = $this->pivot()->where($this->foreignPivotKey, $this->parent->{$this->parentKey});
        if ($ids !== []) {
            $b = $b->whereIn($this->relatedPivotKey, array_values($ids));
        }
        return $b->delete();
    }

    /** @param list<mixed> $ids */
    public function sync(array $ids): array
    {
        $current = $this->pivot()
            ->where($this->foreignPivotKey, $this->parent->{$this->parentKey})
            ->all();
        $currentIds = [];
        foreach ($current as $row) {
            $currentIds[] = $row[$this->relatedPivotKey];
        }
        $attach = array_diff($ids, $currentIds);
        $detach = array_diff($currentIds, $ids);

        if ($detach !== []) { $this->detach(...$detach); }
        if ($attach !== []) { $this->attach(...array_values($attach)); }

        return ['attached' => array_values($attach), 'detached' => array_values($detach)];
    }

    public function getResults(): Collection
    {
        $parentId = $this->parent->{$this->parentKey} ?? null;
        if ($parentId === null) {
            return new Collection();
        }
        $rows = $this->joinedQuery()->where("{$this->pivotTable}.{$this->foreignPivotKey}", $parentId)->all();
        return new Collection($rows);
    }

    public function eager(array $parents): array
    {
        $ids = HasMany::pluckKey($parents, $this->parentKey);
        if ($ids === []) {
            return [];
        }
        return $this->joinedQuery()->whereIn("{$this->pivotTable}.{$this->foreignPivotKey}", $ids)->all();
    }

    public function match(array $parents, array $results, string $relation): void
    {
        $grouped = [];
        foreach ($results as $r) {
            // The join projects the pivot's foreign key onto an extra
            // attribute via $r->extras() ('extras' is the column bag
            // for non-property select results). Hydrator stashes them.
            $extras = $r->extras();
            $pivotParentId = $extras['__pivot_parent'] ?? null;
            if ($pivotParentId !== null) {
                $grouped[(string) $pivotParentId][] = $r;
            }
        }
        foreach ($parents as $p) {
            $pid = $p->{$this->parentKey} ?? null;
            $p->setLoadedRelation($relation, new Collection($grouped[(string) $pid] ?? []));
        }
    }

    private function pivot(): Builder
    {
        return (new Builder(
            Model::connections(),
            new \Silver\Orm\Query\Compiler(Model::connections()),
        ))->from($this->pivotTable);
    }

    /**
     * SELECT related.* plus the pivot's foreign-parent column projected
     * as `__pivot_parent`, so the matcher can group results back.
     */
    private function joinedQuery(): Builder
    {
        /** @var class-string<Model> $cls */
        $cls = $this->relatedClass;
        $relatedTable = $cls::table();

        return $cls::query()
            ->select([
                $relatedTable . '.*',
                new \Silver\Orm\Query\Node\Identifier(
                    "{$this->pivotTable}.{$this->foreignPivotKey}",
                    '__pivot_parent',
                ),
            ])
            ->join(
                $this->pivotTable,
                "{$this->pivotTable}.{$this->relatedPivotKey}",
                '=',
                "{$relatedTable}.{$this->relatedKey}",
            );
    }
}

<?php
declare(strict_types=1);

namespace Silver\Orm\Relations\Morph;

use Silver\Orm\Collection;
use Silver\Orm\Model\Model;
use Silver\Orm\Relations\HasMany;
use Silver\Orm\Relations\Relation;

/**
 * Polymorphic 1:N — parent has many comments where commentable_type
 * = static::class. The related table carries two columns:
 * `{morph}_id` and `{morph}_type`.
 *
 * @template TParent  of Model
 * @template TRelated of Model
 * @extends Relation<TParent, TRelated>
 */
class MorphMany extends Relation
{
    public function __construct(
        Model $parent,
        string $relatedClass,
        protected readonly string $morph,        // e.g. 'attachable'
        protected readonly string $localKey,     // parent PK
    ) {
        parent::__construct($parent, $relatedClass);
    }

    protected function typeColumn(): string { return $this->morph . '_type'; }
    protected function idColumn(): string   { return $this->morph . '_id'; }

    public function getResults(): Collection
    {
        $value = $this->parent->{$this->localKey} ?? null;
        if ($value === null) {
            return new Collection();
        }
        $rows = $this->query()
            ->where($this->idColumn(),   $value)
            ->where($this->typeColumn(), TypeMap::aliasFor($this->parent::class))
            ->all();
        return new Collection($rows);
    }

    public function eager(array $parents): array
    {
        $ids = HasMany::pluckKey($parents, $this->localKey);
        if ($ids === []) {
            return [];
        }
        $alias = TypeMap::aliasFor($this->parent::class);
        return $this->query()
            ->where($this->typeColumn(), $alias)
            ->whereIn($this->idColumn(), $ids)
            ->all();
    }

    public function match(array $parents, array $results, string $relation): void
    {
        $grouped = [];
        foreach ($results as $r) {
            $id = $r->{$this->idColumn()} ?? null;
            if ($id !== null) {
                $grouped[(string) $id][] = $r;
            }
        }
        foreach ($parents as $p) {
            $pk = $p->{$this->localKey} ?? null;
            $p->setLoadedRelation($relation, new Collection($grouped[(string) $pk] ?? []));
        }
    }
}

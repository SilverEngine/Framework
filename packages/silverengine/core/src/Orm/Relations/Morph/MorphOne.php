<?php
declare(strict_types=1);

namespace Silver\Orm\Relations\Morph;

use Silver\Orm\Model\Model;
use Silver\Orm\Relations\HasMany;
use Silver\Orm\Relations\Relation;

/**
 * Polymorphic 1:1. Same lookup shape as MorphMany but the result is
 * a single instance (or null).
 *
 * @template TParent  of Model
 * @template TRelated of Model
 * @extends Relation<TParent, TRelated>
 */
final class MorphOne extends Relation
{
    public function __construct(
        Model $parent,
        string $relatedClass,
        protected readonly string $morph,
        protected readonly string $localKey,
    ) {
        parent::__construct($parent, $relatedClass);
    }

    public function getResults(): ?Model
    {
        $value = $this->parent->{$this->localKey} ?? null;
        if ($value === null) {
            return null;
        }
        return $this->query()
            ->where($this->morph . '_id',   $value)
            ->where($this->morph . '_type', TypeMap::aliasFor($this->parent::class))
            ->first();
    }

    public function eager(array $parents): array
    {
        $ids = HasMany::pluckKey($parents, $this->localKey);
        if ($ids === []) {
            return [];
        }
        return $this->query()
            ->where($this->morph . '_type', TypeMap::aliasFor($this->parent::class))
            ->whereIn($this->morph . '_id', $ids)
            ->all();
    }

    public function match(array $parents, array $results, string $relation): void
    {
        $idCol = $this->morph . '_id';
        $byId  = [];
        foreach ($results as $r) {
            $id = $r->{$idCol} ?? null;
            if ($id !== null && !isset($byId[(string) $id])) {
                $byId[(string) $id] = $r;
            }
        }
        foreach ($parents as $p) {
            $pk = $p->{$this->localKey} ?? null;
            $p->setLoadedRelation($relation, $byId[(string) $pk] ?? null);
        }
    }
}

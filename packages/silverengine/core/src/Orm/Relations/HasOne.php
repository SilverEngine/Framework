<?php
declare(strict_types=1);

namespace Silver\Orm\Relations;

use Silver\Orm\Model\Model;

/**
 * 1:1 from the OWNING side. user->profile: Profile has `user_id`.
 *
 * @template TParent  of Model
 * @template TRelated of Model
 * @extends Relation<TParent, TRelated>
 */
final class HasOne extends Relation
{
    public function __construct(
        Model $parent,
        string $relatedClass,
        protected readonly string $foreignKey,
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
        return $this->query()->where($this->foreignKey, $value)->first();
    }

    public function eager(array $parents): array
    {
        $ids = HasMany::pluckKey($parents, $this->localKey);
        if ($ids === []) {
            return [];
        }
        return $this->query()->whereIn($this->foreignKey, $ids)->all();
    }

    public function match(array $parents, array $results, string $relation): void
    {
        $byKey = [];
        foreach ($results as $r) {
            $fk = $r->{$this->foreignKey} ?? null;
            if ($fk !== null && !isset($byKey[(string) $fk])) {
                $byKey[(string) $fk] = $r;
            }
        }
        foreach ($parents as $p) {
            $pk = $p->{$this->localKey} ?? null;
            $p->setLoadedRelation($relation, $byKey[(string) $pk] ?? null);
        }
    }
}

<?php
declare(strict_types=1);

namespace Silver\Orm\Relations;

use Silver\Orm\Collection;
use Silver\Orm\Model\Model;

/**
 * 1:N from the OWNING side. user->posts: Post has `user_id`, User has `id`.
 *
 * @template TParent  of Model
 * @template TRelated of Model
 * @extends Relation<TParent, TRelated>
 */
class HasMany extends Relation
{
    public function __construct(
        Model $parent,
        string $relatedClass,
        protected readonly string $foreignKey,
        protected readonly string $localKey,
    ) {
        parent::__construct($parent, $relatedClass);
    }

    public function getResults(): Collection
    {
        $value = $this->parent->{$this->localKey} ?? null;
        if ($value === null) {
            return new Collection();
        }
        return new Collection($this->query()->where($this->foreignKey, $value)->all());
    }

    public function eager(array $parents): array
    {
        $ids = self::pluckKey($parents, $this->localKey);
        if ($ids === []) {
            return [];
        }
        return $this->query()->whereIn($this->foreignKey, $ids)->all();
    }

    public function match(array $parents, array $results, string $relation): void
    {
        $grouped = [];
        foreach ($results as $r) {
            $fk = $r->{$this->foreignKey} ?? null;
            if ($fk !== null) {
                $grouped[(string) $fk][] = $r;
            }
        }
        foreach ($parents as $p) {
            $pk = $p->{$this->localKey} ?? null;
            $p->setLoadedRelation($relation, new Collection($grouped[(string) $pk] ?? []));
        }
    }

    /**
     * @param list<Model> $parents
     * @return list<int|string>
     */
    public static function pluckKey(array $parents, string $key): array
    {
        $out = [];
        foreach ($parents as $p) {
            $v = $p->{$key} ?? null;
            if ($v !== null) {
                $out[] = $v;
            }
        }
        return array_values(array_unique($out));
    }
}

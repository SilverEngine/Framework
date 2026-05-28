<?php
declare(strict_types=1);

namespace Silver\Orm\Relations;

use Silver\Orm\Model\Model;

/**
 * Inverse 1:1 / 1:N. user->team: User has `team_id`, Team has `id`.
 *
 * @template TParent  of Model
 * @template TRelated of Model
 * @extends Relation<TParent, TRelated>
 */
final class BelongsTo extends Relation
{
    public function __construct(
        Model $parent,
        string $relatedClass,
        protected readonly string $foreignKey,   // on the parent (eg. team_id)
        protected readonly string $ownerKey,     // on the related (eg. id)
    ) {
        parent::__construct($parent, $relatedClass);
    }

    public function getResults(): ?Model
    {
        $value = $this->parent->{$this->foreignKey} ?? null;
        if ($value === null) {
            return null;
        }
        return $this->query()->where($this->ownerKey, $value)->first();
    }

    public function eager(array $parents): array
    {
        $ids = HasMany::pluckKey($parents, $this->foreignKey);
        if ($ids === []) {
            return [];
        }
        return $this->query()->whereIn($this->ownerKey, $ids)->all();
    }

    public function match(array $parents, array $results, string $relation): void
    {
        $byKey = [];
        foreach ($results as $r) {
            $k = $r->{$this->ownerKey} ?? null;
            if ($k !== null) {
                $byKey[(string) $k] = $r;
            }
        }
        foreach ($parents as $p) {
            $fk = $p->{$this->foreignKey} ?? null;
            $p->setLoadedRelation($relation, $fk !== null ? ($byKey[(string) $fk] ?? null) : null);
        }
    }
}

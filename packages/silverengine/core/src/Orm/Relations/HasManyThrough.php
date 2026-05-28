<?php
declare(strict_types=1);

namespace Silver\Orm\Relations;

use Silver\Orm\Collection;
use Silver\Orm\Model\Model;

/**
 * Distant 1:N via an intermediate model.
 * Country->hasManyThrough(Post::class, User::class):
 *   countries.id → users.country_id → posts.user_id → posts
 *
 * @template TParent  of Model
 * @template TRelated of Model
 * @extends Relation<TParent, TRelated>
 */
final class HasManyThrough extends Relation
{
    public function __construct(
        Model $parent,
        string $relatedClass,
        /** @var class-string<Model> */
        protected readonly string $throughClass,
        protected readonly string $firstKey,        // users.country_id
        protected readonly string $secondKey,       // posts.user_id
        protected readonly string $localKey,        // countries.id
        protected readonly string $secondLocalKey,  // users.id
    ) {
        parent::__construct($parent, $relatedClass);
    }

    public function getResults(): Collection
    {
        $localValue = $this->parent->{$this->localKey} ?? null;
        if ($localValue === null) {
            return new Collection();
        }
        $through = $this->throughClass;
        $throughIds = $through::query()
            ->where($this->firstKey, $localValue)
            ->pluck($this->secondLocalKey);
        if ($throughIds === []) {
            return new Collection();
        }
        return new Collection(
            $this->query()->whereIn($this->secondKey, $throughIds)->all(),
        );
    }

    public function eager(array $parents): array
    {
        $ids = HasMany::pluckKey($parents, $this->localKey);
        if ($ids === []) {
            return [];
        }
        $through = $this->throughClass;

        // Map each through row's intermediate FK back to its parent id.
        // We need this to match results back later.
        $throughRows = $through::query()
            ->select([$this->secondLocalKey, $this->firstKey])
            ->whereIn($this->firstKey, $ids)
            ->all();

        $intermediateToParent = [];
        $intermediateIds      = [];
        foreach ($throughRows as $row) {
            $intId = $row->{$this->secondLocalKey} ?? null;
            $pId   = $row->{$this->firstKey} ?? null;
            if ($intId !== null && $pId !== null) {
                $intermediateToParent[(string) $intId] = $pId;
                $intermediateIds[] = $intId;
            }
        }
        if ($intermediateIds === []) {
            return [];
        }

        $results = $this->query()->whereIn($this->secondKey, $intermediateIds)->all();
        foreach ($results as $r) {
            $intFk = $r->{$this->secondKey} ?? null;
            if ($intFk !== null && isset($intermediateToParent[(string) $intFk])) {
                $r->setExtra('__through_parent', $intermediateToParent[(string) $intFk]);
            }
        }
        return $results;
    }

    public function match(array $parents, array $results, string $relation): void
    {
        $grouped = [];
        foreach ($results as $r) {
            $extras = $r->extras();
            $parentId = $extras['__through_parent'] ?? null;
            if ($parentId !== null) {
                $grouped[(string) $parentId][] = $r;
            }
        }
        foreach ($parents as $p) {
            $pid = $p->{$this->localKey} ?? null;
            $p->setLoadedRelation($relation, new Collection($grouped[(string) $pid] ?? []));
        }
    }
}

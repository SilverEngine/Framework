<?php
declare(strict_types=1);

namespace Silver\Orm\Relations\Morph;

use Silver\Orm\Model\Model;
use Silver\Orm\Relations\Relation;

/**
 * Inverse polymorphic: the child knows {morph}_type + {morph}_id
 * and resolves to the parent model dynamically.
 *
 * @template TParent  of Model
 * @template TRelated of Model
 * @extends Relation<TParent, TRelated>
 */
final class MorphTo extends Relation
{
    public function __construct(
        Model $parent,
        protected readonly string $morph,
    ) {
        // No related class — it's discovered per row.
        parent::__construct($parent, Model::class);
    }

    public function getResults(): ?Model
    {
        $type = $this->parent->{$this->morph . '_type'} ?? null;
        $id   = $this->parent->{$this->morph . '_id'}   ?? null;
        if ($type === null || $id === null) {
            return null;
        }
        /** @var class-string<Model> $cls */
        $cls = TypeMap::classFor((string) $type);
        return $cls::find($id);
    }

    public function eager(array $parents): array
    {
        // Eager loading a MorphTo means grouping parents by type and
        // doing one query per type. Returns a flattened list.
        $byType = [];
        foreach ($parents as $p) {
            $type = $p->{$this->morph . '_type'} ?? null;
            $id   = $p->{$this->morph . '_id'}   ?? null;
            if ($type !== null && $id !== null) {
                $byType[(string) $type][] = $id;
            }
        }
        $results = [];
        foreach ($byType as $type => $ids) {
            /** @var class-string<Model> $cls */
            $cls = TypeMap::classFor($type);
            $rows = $cls::query()->whereIn($cls::primaryKey(), array_values(array_unique($ids)))->all();
            foreach ($rows as $row) {
                $row->setExtra('__morph_type', $type);
                $results[] = $row;
            }
        }
        return $results;
    }

    public function match(array $parents, array $results, string $relation): void
    {
        $byKey = [];
        foreach ($results as $r) {
            $type = $r->extras()['__morph_type'] ?? TypeMap::aliasFor($r::class);
            $pk   = $r->{$r::primaryKey()} ?? null;
            if ($pk !== null) {
                $byKey[$type . ':' . $pk] = $r;
            }
        }
        foreach ($parents as $p) {
            $type = $p->{$this->morph . '_type'} ?? null;
            $id   = $p->{$this->morph . '_id'}   ?? null;
            if ($type === null || $id === null) {
                $p->setLoadedRelation($relation, null);
                continue;
            }
            $p->setLoadedRelation($relation, $byKey[$type . ':' . $id] ?? null);
        }
    }
}

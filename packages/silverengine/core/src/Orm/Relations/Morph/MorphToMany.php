<?php
declare(strict_types=1);

namespace Silver\Orm\Relations\Morph;

use Silver\Orm\Model\Model;
use Silver\Orm\Relations\BelongsToMany;

/**
 * Polymorphic many-to-many via a shared pivot table carrying a type
 * column. post->labels / video->labels both live in `labelables`
 * (label_id, labelable_id, labelable_type); the type column keeps the
 * sides apart.
 *
 * The same class serves the inverse (`morphedByMany`): there the
 * pivot's id+type pair points at the *related* models, so the type
 * value is the related class's alias instead of the parent's.
 *
 * @template TParent  of Model
 * @template TRelated of Model
 * @extends BelongsToMany<TParent, TRelated>
 */
final class MorphToMany extends BelongsToMany
{
    /**
     * @param string       $morphTypeColumn e.g. 'labelable_type'
     * @param class-string $morphClass      class whose TypeMap alias is stored in the type column
     */
    public function __construct(
        Model $parent,
        string $relatedClass,
        string $pivotTable,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey,
        private readonly string $morphTypeColumn,
        private readonly string $morphClass,
    ) {
        parent::__construct(
            $parent,
            $relatedClass,
            $pivotTable,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
        );
    }

    #[\Override]
    protected function extraPivotWhere(): array
    {
        return [$this->morphTypeColumn => TypeMap::aliasFor($this->morphClass)];
    }
}

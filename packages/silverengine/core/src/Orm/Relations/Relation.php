<?php
declare(strict_types=1);

namespace Silver\Orm\Relations;

use Silver\Orm\Model\Model;
use Silver\Orm\Model\ModelBuilder;

/**
 * Base class for every relation kind. Carries the parent model
 * instance + the related model's class so subclasses can build
 * the query and match results back.
 *
 * Subclasses implement:
 *   - getResults()                 — fetch for a single parent
 *   - eager(list<Model> $parents)  — batched fetch for many parents
 *   - match(list<Model> $parents,  Collection $results, string $relation) — attach to parents
 *
 * @template TParent  of Model
 * @template TRelated of Model
 */
abstract class Relation
{
    public function __construct(
        protected readonly Model  $parent,
        /** @var class-string<TRelated> */
        protected readonly string $relatedClass,
    ) {}

    /** Query builder targeting the related model. */
    public function query(): ModelBuilder
    {
        /** @var class-string<Model> $cls */
        $cls = $this->relatedClass;
        return $cls::query();
    }

    /** Fetch for a single parent. */
    abstract public function getResults(): mixed;

    /**
     * Batched eager load.
     *
     * @param  list<TParent> $parents
     * @return list<TRelated>
     */
    abstract public function eager(array $parents): array;

    /**
     * Match the eager-loaded results back to the parent instances,
     * setting `$parent->{$relation}` on each.
     *
     * @param list<TParent>  $parents
     * @param list<TRelated> $results
     */
    abstract public function match(array $parents, array $results, string $relation): void;

    /**
     * Default FK inference: `{snake_class_basename}_id` (e.g. User → user_id).
     */
    public static function defaultForeignKey(string $modelClass): string
    {
        $base = substr($modelClass, (int) strrpos($modelClass, '\\') + 1);
        return self::snake($base) . '_id';
    }

    public static function defaultLocalKey(string $modelClass): string
    {
        /** @var class-string<Model> $modelClass */
        return $modelClass::metadata()->primaryKey ?? 'id';
    }

    private static function snake(string $s): string
    {
        $out = '';
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            if ($i > 0 && ctype_upper($c)) { $out .= '_'; }
            $out .= strtolower($c);
        }
        return $out;
    }
}

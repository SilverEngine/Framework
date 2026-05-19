<?php

declare(strict_types=1);

namespace Silver\Database;

/**
 * The query kinds {@see Query} can build. Backed values match the legacy
 * string `$type` argument of `Query::instance()`; `queryClass()` resolves
 * the same FQN the old `'Silver\Database\Query\' . ucfirst($type)`
 * expression produced, so the factory is a typed drop-in with no
 * behaviour change. Dialect variants are layered on later by
 * {@see Dialect} during compilation, exactly as before.
 */
enum QueryType: string
{
    case Select = 'select';
    case Insert = 'insert';
    case Update = 'update';
    case Delete = 'delete';
    case Create = 'create';
    case Drop   = 'drop';
    case Alter  = 'alter';

    /** @return class-string<Query> */
    public function queryClass(): string
    {
        return 'Silver\\Database\\Query\\' . ucfirst($this->value);
    }

    /** @param array<int,mixed> $args */
    public function make(array $args = []): Query
    {
        $class = $this->queryClass();

        return new $class(...$args);
    }
}

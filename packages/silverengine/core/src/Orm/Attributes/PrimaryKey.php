<?php
declare(strict_types=1);

namespace Silver\Orm\Attributes;

use Attribute;

/**
 * Marks the property that holds this model's primary key. When absent
 * the registry falls back to a property literally named "id" if one
 * exists; otherwise the model has no implicit PK and any attempt at
 * find()/save() that depends on one will throw.
 *
 * Tag two properties for composite primary keys.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final readonly class PrimaryKey
{
    public function __construct(
        public bool $incrementing = true,
    ) {}
}

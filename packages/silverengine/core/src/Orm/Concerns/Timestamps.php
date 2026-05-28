<?php
declare(strict_types=1);

namespace Silver\Orm\Concerns;

use Attribute;

/**
 * Opt the model in to automatic created_at / updated_at maintenance.
 * The Model layer detects this attribute and sets the columns
 * (or properties named created_at/updated_at) on insert/update.
 *
 *   #[Timestamps]
 *   final class User extends Model {}
 *
 * Both columns are stamped with the current UTC time. Override the
 * column names by passing constructor args:
 *
 *   #[Timestamps(createdAt: 'inserted_at', updatedAt: 'modified_at')]
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Timestamps
{
    public function __construct(
        public string $createdAt = 'created_at',
        public string $updatedAt = 'updated_at',
    ) {}
}

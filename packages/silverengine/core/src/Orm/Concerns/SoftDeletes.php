<?php
declare(strict_types=1);

namespace Silver\Orm\Concerns;

use Attribute;

/**
 * Opt in to soft deletes. delete() sets the column (default
 * "deleted_at") to the current UTC time and queries filter it out
 * automatically. forceDelete() bypasses both.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class SoftDeletes
{
    public function __construct(
        public string $column = 'deleted_at',
    ) {}
}

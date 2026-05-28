<?php
declare(strict_types=1);

namespace Silver\Orm\Schema;

/**
 * Standalone index (separate from a column-level ->unique()/->index()).
 * Created via {@see Blueprint::index()} / ::unique() / ::primary()
 * with explicit column lists.
 */
final class IndexDefinition
{
    public const KIND_INDEX   = 'INDEX';
    public const KIND_UNIQUE  = 'UNIQUE';
    public const KIND_PRIMARY = 'PRIMARY';

    /** @param list<string> $columns */
    public function __construct(
        public string  $kind,
        public array   $columns,
        public ?string $name = null,
    ) {}
}

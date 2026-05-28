<?php
declare(strict_types=1);

namespace Silver\Orm\Schema;

/** One row from {@see Migrator::status()}. */
final readonly class MigrationStatus
{
    public function __construct(
        public string  $connection,
        public string  $name,
        public bool    $ran,
        public ?int    $batch,
        public ?string $ranAt,
    ) {}
}

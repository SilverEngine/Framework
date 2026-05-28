<?php
declare(strict_types=1);

namespace Silver\Orm\Schema;

/** One result row from {@see Migrator::run()} / ::rollback(). */
final readonly class MigrationRun
{
    public function __construct(
        public string $connection,
        public string $name,
        public bool   $applied,
        public bool   $pretended,
    ) {}
}

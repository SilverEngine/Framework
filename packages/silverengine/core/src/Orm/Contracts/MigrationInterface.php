<?php
declare(strict_types=1);

namespace Silver\Orm\Contracts;

interface MigrationInterface
{
    /**
     * Name of the connection this migration targets. null = the default
     * connection inferred from the migration directory or the CLI flag.
     */
    public function connection(): ?string;

    public function up(): void;

    public function down(): void;
}

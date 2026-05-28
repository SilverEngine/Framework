<?php
declare(strict_types=1);

namespace Silver\Orm\Schema;

use Silver\Orm\Contracts\MigrationInterface;

/**
 * Migration base class. User migrations are anonymous classes
 * declared as `return new class extends Migration { up(), down() }`
 * and override `protected string $connection` when the migration
 * belongs to a connection other than the file's discovered owner.
 */
abstract class Migration implements MigrationInterface
{
    /**
     * Target connection name. null = use the connection inferred
     * from the migration's directory by the Migrator.
     */
    protected ?string $connection = null;

    /**
     * Set to false to keep the migration outside of a wrapping
     * transaction (raw DDL that doesn't support it, e.g. MySQL).
     */
    public bool $withinTransaction = true;

    public function connection(): ?string
    {
        return $this->connection;
    }

    abstract public function up(): void;

    abstract public function down(): void;
}

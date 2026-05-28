<?php
declare(strict_types=1);

namespace Silver\Orm\Schema;

/**
 * Fluent definition of a foreign key. Started by
 * {@see Blueprint::foreign()} or chained off {@see Blueprint::foreignId()}.
 */
final class ForeignKeyDefinition
{
    public ?string $referencedColumn = null;
    public ?string $referencedTable  = null;
    public ?string $onDelete         = null;
    public ?string $onUpdate         = null;
    public ?string $name             = null;

    /** @param list<string> $columns */
    public function __construct(public array $columns) {}

    public function references(string $column): self { $this->referencedColumn = $column; return $this; }
    public function on(string $table): self          { $this->referencedTable  = $table;  return $this; }
    public function name(string $name): self         { $this->name = $name; return $this; }

    public function cascadeOnDelete(): self  { $this->onDelete = 'CASCADE';  return $this; }
    public function restrictOnDelete(): self { $this->onDelete = 'RESTRICT'; return $this; }
    public function nullOnDelete(): self     { $this->onDelete = 'SET NULL'; return $this; }
    public function noActionOnDelete(): self { $this->onDelete = 'NO ACTION'; return $this; }

    public function cascadeOnUpdate(): self  { $this->onUpdate = 'CASCADE';  return $this; }
    public function restrictOnUpdate(): self { $this->onUpdate = 'RESTRICT'; return $this; }
    public function nullOnUpdate(): self     { $this->onUpdate = 'SET NULL'; return $this; }
}

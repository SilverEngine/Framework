<?php
declare(strict_types=1);

namespace Silver\Orm\Schema;

/**
 * Fluent modifiers for a single column. Returned from every column
 * factory on {@see Blueprint} (string, int, json, …). All mutations
 * happen in place; the Blueprint stores the same instance it gave
 * back, so the grammar reads the final shape at compile time.
 */
final class ColumnDefinition
{
    public bool   $nullable      = false;
    public bool   $unique        = false;
    public bool   $primary       = false;
    public bool   $autoIncrement = false;
    public mixed  $default       = self::NO_DEFAULT;
    public ?string $comment      = null;
    public ?string $after        = null;
    public ?string $generated    = null;
    public ?string $generatedKind = null; // 'STORED' | 'VIRTUAL' | null
    public ?int   $length        = null;
    public ?int   $precision     = null;
    public ?int   $scale         = null;
    /** @var list<string>|null Enum values for enum() columns. */
    public ?array $enumValues    = null;
    public bool   $unsigned      = false;
    public bool   $useCurrent    = false;
    public bool   $useCurrentOnUpdate = false;

    /** Sentinel for "no DEFAULT clause", distinct from null which means DEFAULT NULL. */
    public const string NO_DEFAULT = "\x00__NO_DEFAULT__\x00";

    public function __construct(
        public string $name,
        public string $type,
    ) {}

    public function nullable(bool $on = true): self          { $this->nullable = $on; return $this; }
    public function default(mixed $value): self              { $this->default = $value; return $this; }
    public function unique(bool $on = true): self            { $this->unique = $on; return $this; }
    public function primary(bool $on = true): self           { $this->primary = $on; return $this; }
    public function autoIncrement(bool $on = true): self     { $this->autoIncrement = $on; return $this; }
    public function unsigned(bool $on = true): self          { $this->unsigned = $on; return $this; }
    public function comment(string $text): self              { $this->comment = $text; return $this; }
    public function after(string $column): self              { $this->after = $column; return $this; }
    public function useCurrent(bool $on = true): self        { $this->useCurrent = $on; return $this; }
    public function useCurrentOnUpdate(bool $on = true): self{ $this->useCurrentOnUpdate = $on; return $this; }

    public function generatedAs(string $expression, string $kind = 'STORED'): self
    {
        $this->generated     = $expression;
        $this->generatedKind = strtoupper($kind);
        return $this;
    }

    public function hasDefault(): bool
    {
        return $this->default !== self::NO_DEFAULT;
    }
}

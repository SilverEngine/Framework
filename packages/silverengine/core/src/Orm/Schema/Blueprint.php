<?php
declare(strict_types=1);

namespace Silver\Orm\Schema;

/**
 * Mutable accumulator for a CREATE TABLE or ALTER TABLE definition.
 * The Schema facade hands one to your migration closure; the grammar
 * reads what you put on it.
 */
final class Blueprint
{
    public const ACTION_CREATE = 'create';
    public const ACTION_ALTER  = 'alter';

    public string $action = self::ACTION_CREATE;

    /** @var list<ColumnDefinition> */
    public array $columns = [];

    /** @var list<IndexDefinition> */
    public array $indexes = [];

    /** @var list<ForeignKeyDefinition> */
    public array $foreignKeys = [];

    /** @var list<string> Column names to drop (ALTER only). */
    public array $droppedColumns = [];

    /** @var list<IndexDefinition> Indexes to drop (ALTER only). */
    public array $droppedIndexes = [];

    /** @var list<ForeignKeyDefinition> Foreign keys to drop (ALTER only). */
    public array $droppedForeignKeys = [];

    /** @var list<array{0: string, 1: string}> [old, new] column renames. */
    public array $renamedColumns = [];

    public ?string $engine  = null;   // MySQL
    public ?string $charset = null;   // MySQL

    public function __construct(
        public string $table,
    ) {}

    // ---------- numeric ----------

    public function id(string $name = 'id'): ColumnDefinition
    {
        return $this->bigIncrements($name);
    }

    public function increments(string $name = 'id'): ColumnDefinition
    {
        $col = $this->add($name, 'integer')->unsigned()->autoIncrement()->primary();
        return $col;
    }

    public function bigIncrements(string $name = 'id'): ColumnDefinition
    {
        return $this->add($name, 'bigint')->unsigned()->autoIncrement()->primary();
    }

    public function tinyInt(string $name): ColumnDefinition   { return $this->add($name, 'tinyint'); }
    public function smallInt(string $name): ColumnDefinition  { return $this->add($name, 'smallint'); }
    public function int(string $name): ColumnDefinition       { return $this->add($name, 'integer'); }
    public function bigInt(string $name): ColumnDefinition    { return $this->add($name, 'bigint'); }
    public function unsignedBigInt(string $name): ColumnDefinition { return $this->bigInt($name)->unsigned(); }

    public function float(string $name): ColumnDefinition { return $this->add($name, 'float'); }
    public function double(string $name): ColumnDefinition { return $this->add($name, 'double'); }

    public function decimal(string $name, int $precision = 10, int $scale = 2): ColumnDefinition
    {
        $col = $this->add($name, 'decimal');
        $col->precision = $precision;
        $col->scale     = $scale;
        return $col;
    }

    // ---------- string ----------

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        $col = $this->add($name, 'string');
        $col->length = $length;
        return $col;
    }

    public function char(string $name, int $length = 1): ColumnDefinition
    {
        $col = $this->add($name, 'char');
        $col->length = $length;
        return $col;
    }

    public function text(string $name): ColumnDefinition       { return $this->add($name, 'text'); }
    public function mediumText(string $name): ColumnDefinition { return $this->add($name, 'mediumtext'); }
    public function longText(string $name): ColumnDefinition   { return $this->add($name, 'longtext'); }

    /** @param list<string> $values */
    public function enum(string $name, array $values): ColumnDefinition
    {
        $col = $this->add($name, 'enum');
        $col->enumValues = $values;
        return $col;
    }

    // ---------- other ----------

    public function bool(string $name): ColumnDefinition       { return $this->add($name, 'boolean'); }
    public function date(string $name): ColumnDefinition       { return $this->add($name, 'date'); }
    public function dateTime(string $name): ColumnDefinition   { return $this->add($name, 'datetime'); }
    public function time(string $name): ColumnDefinition       { return $this->add($name, 'time'); }
    public function timestamp(string $name): ColumnDefinition  { return $this->add($name, 'timestamp'); }
    public function json(string $name): ColumnDefinition       { return $this->add($name, 'json'); }
    public function jsonb(string $name): ColumnDefinition      { return $this->add($name, 'jsonb'); }
    public function uuid(string $name): ColumnDefinition       { return $this->add($name, 'uuid'); }
    public function binary(string $name): ColumnDefinition     { return $this->add($name, 'blob'); }

    // ---------- composites ----------

    /**
     * Add nullable created_at + updated_at TIMESTAMP columns.
     */
    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    /**
     * Add a nullable deleted_at TIMESTAMP for soft deletes.
     */
    public function softDeletes(string $name = 'deleted_at'): ColumnDefinition
    {
        return $this->timestamp($name)->nullable();
    }

    /**
     * Adds `{name}_id` (unsigned bigint) — chain ->references()->on()
     * to constrain it. Adds an index automatically.
     */
    public function foreignId(string $name): ColumnDefinition
    {
        return $this->unsignedBigInt($name);
    }

    /**
     * Polymorphic `{name}_id` + `{name}_type` pair with a composite
     * index for fast lookups by parent.
     */
    public function morphs(string $name): void
    {
        $this->string($name . '_type');
        $this->unsignedBigInt($name . '_id');
        $this->index([$name . '_type', $name . '_id'], $name . '_index');
    }

    // ---------- indexes / constraints ----------

    /** @param list<string>|string $columns */
    public function index(array|string $columns, ?string $name = null): IndexDefinition
    {
        $idx = new IndexDefinition(IndexDefinition::KIND_INDEX, (array) $columns, $name);
        $this->indexes[] = $idx;
        return $idx;
    }

    /** @param list<string>|string $columns */
    public function uniqueIndex(array|string $columns, ?string $name = null): IndexDefinition
    {
        $idx = new IndexDefinition(IndexDefinition::KIND_UNIQUE, (array) $columns, $name);
        $this->indexes[] = $idx;
        return $idx;
    }

    /** @param list<string>|string $columns */
    public function primary(array|string $columns, ?string $name = null): IndexDefinition
    {
        $idx = new IndexDefinition(IndexDefinition::KIND_PRIMARY, (array) $columns, $name);
        $this->indexes[] = $idx;
        return $idx;
    }

    /** @param list<string>|string $columns */
    public function foreign(array|string $columns): ForeignKeyDefinition
    {
        $fk = new ForeignKeyDefinition((array) $columns);
        $this->foreignKeys[] = $fk;
        return $fk;
    }

    // ---------- alter-only ----------

    public function dropColumn(string ...$names): void
    {
        foreach ($names as $n) {
            $this->droppedColumns[] = $n;
        }
    }

    /** @param list<string>|string $columns */
    public function dropIndex(array|string $columns, ?string $name = null): void
    {
        $this->droppedIndexes[] = new IndexDefinition(IndexDefinition::KIND_INDEX, (array) $columns, $name);
    }

    /** @param list<string>|string $columns */
    public function dropUnique(array|string $columns, ?string $name = null): void
    {
        $this->droppedIndexes[] = new IndexDefinition(IndexDefinition::KIND_UNIQUE, (array) $columns, $name);
    }

    /** @param list<string>|string $columns */
    public function dropForeign(array|string $columns, ?string $name = null): void
    {
        $fk = new ForeignKeyDefinition((array) $columns);
        $fk->name = $name;
        $this->droppedForeignKeys[] = $fk;
    }

    public function renameColumn(string $old, string $new): void
    {
        $this->renamedColumns[] = [$old, $new];
    }

    // ---------- mysql hints ----------

    public function engine(string $name): self  { $this->engine = $name; return $this; }
    public function charset(string $name): self { $this->charset = $name; return $this; }

    // ---------- internals ----------

    private function add(string $name, string $type): ColumnDefinition
    {
        $col = new ColumnDefinition($name, $type);
        $this->columns[] = $col;
        return $col;
    }
}

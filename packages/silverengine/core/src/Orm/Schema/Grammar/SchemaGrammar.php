<?php
declare(strict_types=1);

namespace Silver\Orm\Schema\Grammar;

use Silver\Orm\Connection\Driver;
use Silver\Orm\Contracts\SchemaGrammarInterface;
use Silver\Orm\Schema\Blueprint;
use Silver\Orm\Schema\ColumnDefinition;
use Silver\Orm\Schema\ForeignKeyDefinition;
use Silver\Orm\Schema\IndexDefinition;

/**
 * Shared schema-compilation scaffolding. Driver subclasses override
 * column type mapping ({@see typeFor()}) and table-level quirks
 * (engine/charset, ALTER strategy).
 */
abstract class SchemaGrammar implements SchemaGrammarInterface
{
    abstract public function driver(): Driver;

    /** Driver-specific SQL type for an ORM column type ('string', 'json', …). */
    abstract protected function typeFor(ColumnDefinition $col): string;

    // ---------- create / drop / rename / has ----------

    /** @return list<string> */
    public function compileCreate(Blueprint $b): array
    {
        $cols = [];
        foreach ($b->columns as $c) {
            $cols[] = $this->compileColumn($c);
        }
        foreach ($b->indexes as $idx) {
            if ($idx->kind === IndexDefinition::KIND_PRIMARY) {
                $cols[] = 'PRIMARY KEY (' . $this->quoteColumns($idx->columns) . ')';
            } elseif ($idx->kind === IndexDefinition::KIND_UNIQUE && $idx->name === null) {
                $cols[] = 'UNIQUE (' . $this->quoteColumns($idx->columns) . ')';
            }
        }
        foreach ($b->foreignKeys as $fk) {
            $cols[] = $this->compileInlineForeignKey($fk);
        }

        $sql = 'CREATE TABLE ' . $this->quote($b->table) . ' ('
            . implode(', ', $cols) . ')';
        $sql .= $this->tableSuffix($b);

        $statements = [$sql];

        // Named UNIQUE + non-unique indexes go out as separate CREATE INDEX.
        foreach ($b->indexes as $idx) {
            if ($idx->kind === IndexDefinition::KIND_INDEX
                || ($idx->kind === IndexDefinition::KIND_UNIQUE && $idx->name !== null)) {
                $statements[] = $this->compileCreateIndex($b->table, $idx);
            }
        }

        return $statements;
    }

    public function compileDrop(string $table): string
    {
        return 'DROP TABLE ' . $this->quote($table);
    }

    public function compileDropIfExists(string $table): string
    {
        return 'DROP TABLE IF EXISTS ' . $this->quote($table);
    }

    public function compileRename(string $from, string $to): string
    {
        return 'ALTER TABLE ' . $this->quote($from) . ' RENAME TO ' . $this->quote($to);
    }

    // ---------- alter ----------

    /** @return list<string> */
    public function compileAlter(Blueprint $b): array
    {
        $out = [];

        foreach ($b->columns as $col) {
            $out[] = 'ALTER TABLE ' . $this->quote($b->table)
                . ' ADD COLUMN ' . $this->compileColumn($col);
        }

        foreach ($b->renamedColumns as [$old, $new]) {
            $out[] = 'ALTER TABLE ' . $this->quote($b->table)
                . ' RENAME COLUMN ' . $this->quote($old) . ' TO ' . $this->quote($new);
        }

        foreach ($b->droppedColumns as $col) {
            $out[] = 'ALTER TABLE ' . $this->quote($b->table) . ' DROP COLUMN ' . $this->quote($col);
        }

        foreach ($b->indexes as $idx) {
            $out[] = $this->compileCreateIndex($b->table, $idx);
        }

        foreach ($b->droppedIndexes as $idx) {
            $name = $idx->name ?? $this->defaultIndexName($b->table, $idx);
            $out[] = 'DROP INDEX ' . $this->quote($name);
        }

        return $out;
    }

    // ---------- introspection (driver-specific) ----------

    abstract public function compileHasTable(string $table): string;

    abstract public function compileHasColumn(string $table): string;

    // ---------- pieces ----------

    protected function compileColumn(ColumnDefinition $c): string
    {
        $parts = [$this->quote($c->name), $this->typeFor($c)];

        if ($c->generated !== null) {
            $parts[] = 'GENERATED ALWAYS AS (' . $c->generated . ') ' . ($c->generatedKind ?? 'STORED');
        }

        if (!$c->nullable) {
            $parts[] = 'NOT NULL';
        }

        if ($c->hasDefault()) {
            $parts[] = 'DEFAULT ' . $this->defaultValue($c);
        } elseif ($c->useCurrent && in_array($c->type, ['timestamp', 'datetime'], true)) {
            $parts[] = 'DEFAULT CURRENT_TIMESTAMP';
        }

        if ($c->unique && !$c->primary) {
            $parts[] = 'UNIQUE';
        }

        return implode(' ', $parts);
    }

    protected function compileInlineForeignKey(ForeignKeyDefinition $fk): string
    {
        if ($fk->referencedTable === null || $fk->referencedColumn === null) {
            throw new \LogicException(
                'Foreign key on (' . implode(',', $fk->columns) . ') is missing references()/on().'
            );
        }
        $sql = 'FOREIGN KEY (' . $this->quoteColumns($fk->columns) . ') '
            . 'REFERENCES ' . $this->quote($fk->referencedTable)
            . ' (' . $this->quote($fk->referencedColumn) . ')';
        if ($fk->onDelete !== null) { $sql .= ' ON DELETE ' . $fk->onDelete; }
        if ($fk->onUpdate !== null) { $sql .= ' ON UPDATE ' . $fk->onUpdate; }
        return $sql;
    }

    protected function compileCreateIndex(string $table, IndexDefinition $idx): string
    {
        $unique = $idx->kind === IndexDefinition::KIND_UNIQUE ? 'UNIQUE ' : '';
        $name   = $idx->name ?? $this->defaultIndexName($table, $idx);
        return 'CREATE ' . $unique . 'INDEX ' . $this->quote($name)
            . ' ON ' . $this->quote($table)
            . ' (' . $this->quoteColumns($idx->columns) . ')';
    }

    protected function defaultIndexName(string $table, IndexDefinition $idx): string
    {
        $suffix = match ($idx->kind) {
            IndexDefinition::KIND_UNIQUE => 'unique',
            IndexDefinition::KIND_INDEX  => 'index',
            default                      => 'idx',
        };
        return $table . '_' . implode('_', $idx->columns) . '_' . $suffix;
    }

    protected function defaultValue(ColumnDefinition $c): string
    {
        $v = $c->default;
        if ($v === null)         { return 'NULL'; }
        if (is_bool($v))         { return $v ? '1' : '0'; }
        if (is_int($v) || is_float($v)) { return (string) $v; }
        return $this->literal((string) $v);
    }

    protected function tableSuffix(Blueprint $b): string
    {
        return '';
    }

    protected function quote(string $name): string
    {
        return $this->driver()->quoteIdentifier($name);
    }

    /** @param list<string> $columns */
    protected function quoteColumns(array $columns): string
    {
        return implode(', ', array_map($this->quote(...), $columns));
    }

    protected function literal(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}

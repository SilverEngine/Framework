<?php
declare(strict_types=1);

namespace Silver\Orm\Schema\Grammar;

use Silver\Orm\Connection\Driver;
use Silver\Orm\Schema\ColumnDefinition;

final class SqliteSchemaGrammar extends SchemaGrammar
{
    public function driver(): Driver { return Driver::Sqlite; }

    protected function typeFor(ColumnDefinition $c): string
    {
        return match ($c->type) {
            // Sqlite is dynamically typed; column declarations are
            // mostly hints used by the type-affinity rules. We map to
            // the canonical affinity keywords.
            'integer', 'tinyint', 'smallint', 'bigint' => $c->autoIncrement
                ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
                : 'INTEGER',
            'float', 'double'  => 'REAL',
            'decimal'          => 'NUMERIC',
            'boolean'          => 'INTEGER',
            'string', 'char'   => 'TEXT',
            'text', 'mediumtext', 'longtext' => 'TEXT',
            'enum'             => 'TEXT',
            'json', 'jsonb'    => 'TEXT',
            'uuid'             => 'TEXT',
            'date', 'datetime', 'timestamp', 'time' => 'TEXT',
            'blob'             => 'BLOB',
            default            => strtoupper($c->type),
        };
    }

    /**
     * Sqlite swallows the column-level PRIMARY KEY when the column type
     * already declared it (autoIncrement path). Other rules from the
     * parent stay in effect.
     */
    protected function compileColumn(ColumnDefinition $c): string
    {
        if ($c->autoIncrement) {
            // INTEGER PRIMARY KEY AUTOINCREMENT is the entire definition.
            // NOT NULL / DEFAULT are implicit (rowid alias).
            return $this->quote($c->name) . ' ' . $this->typeFor($c);
        }
        return parent::compileColumn($c);
    }

    public function compileHasTable(string $table): string
    {
        return 'SELECT 1 FROM sqlite_master WHERE type = ' . $this->literal('table')
            . ' AND name = ' . $this->literal($table) . ' LIMIT 1';
    }

    public function compileHasColumn(string $table): string
    {
        return 'PRAGMA table_info(' . $this->literal($table) . ')';
    }
}

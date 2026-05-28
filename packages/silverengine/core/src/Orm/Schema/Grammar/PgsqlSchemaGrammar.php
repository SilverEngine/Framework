<?php
declare(strict_types=1);

namespace Silver\Orm\Schema\Grammar;

use Silver\Orm\Connection\Driver;
use Silver\Orm\Schema\ColumnDefinition;

final class PgsqlSchemaGrammar extends SchemaGrammar
{
    public function driver(): Driver { return Driver::Pgsql; }

    protected function typeFor(ColumnDefinition $c): string
    {
        return match ($c->type) {
            // Postgres has SMALLSERIAL/SERIAL/BIGSERIAL for autoincrement
            // — but those are pseudo-types that resolve to integer +
            // sequence. We emit them only when autoIncrement is on.
            'tinyint'    => 'SMALLINT',
            'smallint'   => $c->autoIncrement ? 'SMALLSERIAL' : 'SMALLINT',
            'integer'    => $c->autoIncrement ? 'SERIAL'      : 'INTEGER',
            'bigint'     => $c->autoIncrement ? 'BIGSERIAL'   : 'BIGINT',
            'float'      => 'REAL',
            'double'     => 'DOUBLE PRECISION',
            'decimal'    => sprintf('NUMERIC(%d, %d)', $c->precision ?? 10, $c->scale ?? 2),
            'boolean'    => 'BOOLEAN',
            'string'     => sprintf('VARCHAR(%d)', $c->length ?? 255),
            'char'       => sprintf('CHAR(%d)',    $c->length ?? 1),
            'text',
            'mediumtext',
            'longtext'   => 'TEXT',
            'enum'       => sprintf('TEXT CHECK ("%s" IN (%s))', $c->name, implode(', ', array_map($this->literal(...), $c->enumValues ?? []))),
            'json'       => 'JSON',
            'jsonb'      => 'JSONB',
            'uuid'       => 'UUID',
            'date'       => 'DATE',
            'datetime',
            'timestamp'  => 'TIMESTAMP',
            'time'       => 'TIME',
            'blob'       => 'BYTEA',
            default      => strtoupper($c->type),
        };
    }

    #[\Override]
    protected function compileColumn(ColumnDefinition $c): string
    {
        // SERIAL types already encode autoincrement + NOT NULL behaviour.
        if ($c->autoIncrement) {
            return $this->quote($c->name) . ' ' . $this->typeFor($c) . ' PRIMARY KEY';
        }
        return parent::compileColumn($c);
    }

    public function compileHasTable(string $table): string
    {
        return 'SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema()'
            . ' AND table_name = ' . $this->literal($table) . ' LIMIT 1';
    }

    public function compileHasColumn(string $table): string
    {
        return 'SELECT column_name AS name FROM information_schema.columns '
            . 'WHERE table_schema = current_schema() AND table_name = ' . $this->literal($table);
    }
}

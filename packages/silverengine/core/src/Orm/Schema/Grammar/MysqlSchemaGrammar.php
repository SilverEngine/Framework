<?php
declare(strict_types=1);

namespace Silver\Orm\Schema\Grammar;

use Silver\Orm\Schema\IndexDefinition;
use Silver\Orm\Connection\Driver;
use Silver\Orm\Schema\Blueprint;
use Silver\Orm\Schema\ColumnDefinition;

final class MysqlSchemaGrammar extends SchemaGrammar
{
    public function driver(): Driver { return Driver::Mysql; }

    protected function typeFor(ColumnDefinition $c): string
    {
        return match ($c->type) {
            'tinyint'    => $c->unsigned ? 'TINYINT UNSIGNED'    : 'TINYINT',
            'smallint'   => $c->unsigned ? 'SMALLINT UNSIGNED'   : 'SMALLINT',
            'integer'    => $c->unsigned ? 'INT UNSIGNED'        : 'INT',
            'bigint'     => $c->unsigned ? 'BIGINT UNSIGNED'     : 'BIGINT',
            'float'      => 'FLOAT',
            'double'     => 'DOUBLE',
            'decimal'    => sprintf('DECIMAL(%d, %d)', $c->precision ?? 10, $c->scale ?? 2),
            'boolean'    => 'TINYINT(1)',
            'string'     => sprintf('VARCHAR(%d)',   $c->length ?? 255),
            'char'       => sprintf('CHAR(%d)',      $c->length ?? 1),
            'text'       => 'TEXT',
            'mediumtext' => 'MEDIUMTEXT',
            'longtext'   => 'LONGTEXT',
            'enum'       => 'ENUM(' . implode(', ', array_map($this->literal(...), $c->enumValues ?? [])) . ')',
            'json',
            'jsonb'      => 'JSON',
            'uuid'       => 'CHAR(36)',
            'date'       => 'DATE',
            'datetime'   => 'DATETIME',
            'time'       => 'TIME',
            'timestamp'  => 'TIMESTAMP',
            'blob'       => 'BLOB',
            default      => strtoupper($c->type),
        };
    }

    #[\Override]
    protected function compileColumn(ColumnDefinition $c): string
    {
        // MySQL emits column-level AUTO_INCREMENT alongside the type.
        // PK on the column lives at the table level (PRIMARY KEY (col)).
        $parts = [$this->quote($c->name), $this->typeFor($c)];

        if ($c->generated !== null) {
            $parts[] = 'GENERATED ALWAYS AS (' . $c->generated . ') ' . ($c->generatedKind ?? 'STORED');
        }

        if (!$c->nullable) {
            $parts[] = 'NOT NULL';
        }

        if ($c->autoIncrement) {
            $parts[] = 'AUTO_INCREMENT';
        }

        if ($c->hasDefault()) {
            $parts[] = 'DEFAULT ' . $this->defaultValue($c);
        } elseif ($c->useCurrent && in_array($c->type, ['timestamp', 'datetime'], true)) {
            $parts[] = 'DEFAULT CURRENT_TIMESTAMP';
        }

        if ($c->useCurrentOnUpdate && in_array($c->type, ['timestamp', 'datetime'], true)) {
            $parts[] = 'ON UPDATE CURRENT_TIMESTAMP';
        }

        if ($c->unique && !$c->primary) {
            $parts[] = 'UNIQUE';
        }

        if ($c->comment !== null) {
            $parts[] = 'COMMENT ' . $this->literal($c->comment);
        }

        if ($c->after !== null) {
            $parts[] = 'AFTER ' . $this->quote($c->after);
        }

        return implode(' ', $parts);
    }

    #[\Override]
    public function compileCreate(Blueprint $b): array
    {
        // Promote auto-increment columns to a table-level PRIMARY KEY
        // since MySQL requires them as PK on InnoDB.
        $autoIncrement = null;
        foreach ($b->columns as $c) {
            if ($c->autoIncrement) {
                $autoIncrement = $c->name;
                break;
            }
        }
        if ($autoIncrement !== null) {
            $hasPk = array_any($b->indexes, fn($i): bool => $i->kind === IndexDefinition::KIND_PRIMARY);
            if (!$hasPk) {
                $b->primary([$autoIncrement]);
            }
        }

        return parent::compileCreate($b);
    }

    #[\Override]
    protected function tableSuffix(Blueprint $b): string
    {
        $bits = [];
        if ($b->engine  !== null) { $bits[] = 'ENGINE='  . $b->engine; }
        if ($b->charset !== null) { $bits[] = 'DEFAULT CHARSET=' . $b->charset; }
        if ($bits === []) {
            $bits[] = 'ENGINE=InnoDB';
            $bits[] = 'DEFAULT CHARSET=utf8mb4';
        }
        return ' ' . implode(' ', $bits);
    }

    public function compileHasTable(string $table): string
    {
        return 'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE()'
            . ' AND table_name = ' . $this->literal($table) . ' LIMIT 1';
    }

    public function compileHasColumn(string $table): string
    {
        return 'SELECT column_name AS name FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = ' . $this->literal($table);
    }
}

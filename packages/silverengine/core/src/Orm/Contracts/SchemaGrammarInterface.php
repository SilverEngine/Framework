<?php
declare(strict_types=1);

namespace Silver\Orm\Contracts;

use Silver\Orm\Connection\Driver;
use Silver\Orm\Schema\Blueprint;

/**
 * Compiles a Blueprint into one or more DDL statements. Returns a
 * list — sqlite often needs multiple statements to alter (rebuild
 * the table); mysql/pgsql typically produce one CREATE / ALTER.
 */
interface SchemaGrammarInterface
{
    public function driver(): Driver;

    /** @return list<string> */
    public function compileCreate(Blueprint $blueprint): array;

    /** @return list<string> */
    public function compileAlter(Blueprint $blueprint): array;

    public function compileDrop(string $table): string;

    public function compileDropIfExists(string $table): string;

    public function compileRename(string $from, string $to): string;

    public function compileHasTable(string $table): string;

    public function compileHasColumn(string $table): string;
}

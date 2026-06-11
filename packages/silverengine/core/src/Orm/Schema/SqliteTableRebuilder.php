<?php
declare(strict_types=1);

namespace Silver\Orm\Schema;

use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Schema\Grammar\SqliteSchemaGrammar;

/**
 * Sqlite cannot redefine a column in place, so ->change() is realised
 * as the documented rebuild dance:
 *
 *   1. introspect the live table (PRAGMA table_info / index_list /
 *      foreign_key_list + the stored CREATE TABLE sql)
 *   2. CREATE a temp table with the changed columns swapped in
 *   3. INSERT … SELECT the data across
 *   4. DROP the old table, RENAME the temp into place
 *   5. re-issue the CREATE INDEX statements sqlite_master kept
 *
 * Unchanged columns keep their declared type, NOT NULL, DEFAULT and
 * primary-key shape verbatim; UNIQUE constraints survive as table-level
 * UNIQUE(...) clauses, named indexes via their original SQL.
 */
final readonly class SqliteTableRebuilder
{
    public function __construct(
        private ConnectionManager $cm,
        private ?string           $connection,
    ) {}

    public function rebuild(Blueprint $bp): void
    {
        $table   = $bp->table;
        $grammar = new SqliteSchemaGrammar();
        $quote   = fn (string $n): string => $grammar->driver()->quoteIdentifier($n);

        $changes = [];
        foreach ($bp->changedColumns() as $c) {
            $changes[$c->name] = $c;
        }

        $info = $this->cm->raw("PRAGMA table_info('{$table}')", [], $this->connection)
            ->fetchAll(\PDO::FETCH_ASSOC);
        if ($info === []) {
            throw new \RuntimeException("Cannot rebuild unknown table '{$table}'.");
        }

        $names = array_column($info, 'name');
        foreach (array_keys($changes) as $changed) {
            if (!in_array($changed, $names, true)) {
                throw new \RuntimeException(
                    "Cannot change unknown column '{$changed}' on table '{$table}'.",
                );
            }
        }

        $createSql        = (string) $this->cm->raw(
            "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?",
            [$table],
            $this->connection,
        )->fetchColumn();
        $hasAutoincrement = stripos($createSql, 'AUTOINCREMENT') !== false;

        // ---- column definitions for the rebuilt table ----
        $defs    = [];
        $pkCols  = [];
        foreach ($info as $col) {
            $name = (string) $col['name'];
            if (isset($changes[$name])) {
                $defs[] = $grammar->compileColumnDefinition($changes[$name]);
                continue;
            }
            if ((int) $col['pk'] === 1 && strtoupper((string) $col['type']) === 'INTEGER') {
                // rowid alias — keep it inline so autoincrement survives.
                $defs[] = $quote($name) . ' INTEGER PRIMARY KEY'
                    . ($hasAutoincrement ? ' AUTOINCREMENT' : '');
                continue;
            }
            if ((int) $col['pk'] > 0) {
                $pkCols[] = $name;
            }
            $def = $quote($name) . ' ' . (string) $col['type'];
            if ((int) $col['notnull'] === 1) {
                $def .= ' NOT NULL';
            }
            if ($col['dflt_value'] !== null) {
                // pragma reports the default exactly as declared (already quoted).
                $def .= ' DEFAULT ' . (string) $col['dflt_value'];
            }
            $defs[] = $def;
        }
        if ($pkCols !== []) {
            $defs[] = 'PRIMARY KEY (' . implode(', ', array_map($quote, $pkCols)) . ')';
        }

        // ---- table-level UNIQUE constraints + named indexes ----
        $indexSql = [];
        $indexes  = $this->cm->raw("PRAGMA index_list('{$table}')", [], $this->connection)
            ->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($indexes as $idx) {
            $origin = (string) ($idx['origin'] ?? 'c');
            if ($origin === 'pk') {
                continue;
            }
            if ($origin === 'u') {
                // Inline UNIQUE became an auto-index with no stored SQL —
                // recreate it as a table-level constraint.
                $cols = $this->cm->raw("PRAGMA index_info('{$idx['name']}')", [], $this->connection)
                    ->fetchAll(\PDO::FETCH_ASSOC);
                usort($cols, fn (array $a, array $b): int => (int) $a['seqno'] <=> (int) $b['seqno']);
                $defs[] = 'UNIQUE (' . implode(', ', array_map(
                    fn (array $c): string => $quote((string) $c['name']),
                    $cols,
                )) . ')';
                continue;
            }
            // Named CREATE INDEX — replay its original SQL after the rename.
            $sql = $this->cm->raw(
                "SELECT sql FROM sqlite_master WHERE type = 'index' AND name = ?",
                [(string) $idx['name']],
                $this->connection,
            )->fetchColumn();
            if (is_string($sql) && $sql !== '') {
                $indexSql[] = $sql;
            }
        }

        // ---- foreign keys ----
        $fks = $this->cm->raw("PRAGMA foreign_key_list('{$table}')", [], $this->connection)
            ->fetchAll(\PDO::FETCH_ASSOC);
        $byId = [];
        foreach ($fks as $fk) {
            $byId[(int) $fk['id']][] = $fk;
        }
        foreach ($byId as $group) {
            usort($group, fn (array $a, array $b): int => (int) $a['seq'] <=> (int) $b['seq']);
            $from = array_map(fn (array $f): string => $quote((string) $f['from']), $group);
            $to   = array_map(fn (array $f): string => $quote((string) $f['to']), $group);
            $def  = 'FOREIGN KEY (' . implode(', ', $from) . ') REFERENCES '
                . $quote((string) $group[0]['table']) . ' (' . implode(', ', $to) . ')';
            if (($onDelete = strtoupper((string) $group[0]['on_delete'])) !== 'NO ACTION') {
                $def .= ' ON DELETE ' . $onDelete;
            }
            if (($onUpdate = strtoupper((string) $group[0]['on_update'])) !== 'NO ACTION') {
                $def .= ' ON UPDATE ' . $onUpdate;
            }
            $defs[] = $def;
        }

        // ---- the dance ----
        $tmp        = '__rebuild_' . $table;
        $columnList = implode(', ', array_map($quote, $names));

        $this->cm->exec('CREATE TABLE ' . $quote($tmp) . ' (' . implode(', ', $defs) . ')', $this->connection);
        $this->cm->exec(
            'INSERT INTO ' . $quote($tmp) . ' (' . $columnList . ') SELECT ' . $columnList
                . ' FROM ' . $quote($table),
            $this->connection,
        );
        $this->cm->exec('DROP TABLE ' . $quote($table), $this->connection);
        $this->cm->exec('ALTER TABLE ' . $quote($tmp) . ' RENAME TO ' . $quote($table), $this->connection);
        foreach ($indexSql as $sql) {
            $this->cm->exec($sql, $this->connection);
        }
    }
}

<?php
declare(strict_types=1);

namespace Silver\Orm\Query;

/**
 * Pretty-print rows from EXPLAIN/EXPLAIN QUERY PLAN/EXPLAIN ANALYZE.
 * Layout is the same shape across drivers — column-aligned table with
 * the column names from the first row as the header. Driver-specific
 * structured output (pgsql JSON, mysql TREE) is rendered verbatim
 * when it's a single-column-single-row payload.
 */
final class PlanFormatter
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    public static function format(string $originalSql, array $rows, ?float $totalMs): string
    {
        $header = "Query: {$originalSql}";
        if ($totalMs !== null) {
            $header .= sprintf("\nTotal: %.3f ms", $totalMs);
        }

        if ($rows === []) {
            return $header . "\n(no plan rows)";
        }

        // Single-row, single-column payloads (mysql FORMAT=TREE, pgsql
        // FORMAT JSON) get rendered as-is — they already self-format.
        if (count($rows) === 1) {
            $only = $rows[0];
            if (count($only) === 1) {
                $value = (string) array_values($only)[0];
                return $header . "\n" . $value;
            }
        }

        $columns = array_keys($rows[0]);
        $widths  = [];
        foreach ($columns as $c) {
            $widths[$c] = max(strlen($c), self::maxLen($rows, $c));
        }

        $line = '+';
        foreach ($columns as $c) {
            $line .= str_repeat('-', $widths[$c] + 2) . '+';
        }

        $out  = [$header, $line];
        $head = '|';
        foreach ($columns as $c) {
            $head .= ' ' . str_pad($c, $widths[$c]) . ' |';
        }
        $out[] = $head;
        $out[] = $line;

        foreach ($rows as $row) {
            $rowOut = '|';
            foreach ($columns as $c) {
                $rowOut .= ' ' . str_pad((string) ($row[$c] ?? ''), $widths[$c]) . ' |';
            }
            $out[] = $rowOut;
        }
        $out[] = $line;

        return implode("\n", $out);
    }

    /** @param list<array<string, mixed>> $rows */
    private static function maxLen(array $rows, string $col): int
    {
        $max = 0;
        foreach ($rows as $r) {
            $len = strlen((string) ($r[$col] ?? ''));
            if ($len > $max) {
                $max = $len;
            }
        }
        return $max;
    }
}

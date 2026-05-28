<?php
declare(strict_types=1);

namespace Silver\Orm\Query\Grammar;

use Silver\Orm\Connection\Driver;
use Silver\Orm\Contracts\GrammarInterface;
use Silver\Orm\Query\Node\Binding;
use Silver\Orm\Query\Node\Expression;
use Silver\Orm\Query\Node\Identifier;
use Silver\Orm\Query\Node\Join;
use Silver\Orm\Query\Node\Node;
use Silver\Orm\Query\Node\Raw;
use Silver\Orm\Query\Node\Subquery;
use Silver\Orm\Query\QueryState;

/**
 * Base SQL compiler. Walks a QueryState and emits [sql, bindings].
 * Driver subclasses override only the parts that differ — identifier
 * quoting, LIMIT shape, EXPLAIN prefix, INSERT-multirow form.
 *
 * Pure: same state in → same output. No PDO contact, no model awareness,
 * no globals.
 */
abstract class Grammar implements GrammarInterface
{
    abstract public function driver(): Driver;

    public function explainPrefix(): string { return 'EXPLAIN '; }
    public function analyzePrefix(): string { return 'EXPLAIN '; }

    /** @return array{0: string, 1: list<mixed>} */
    public function compileSelect(object $state): array
    {
        if (!$state instanceof QueryState) {
            throw new \InvalidArgumentException('compileSelect requires QueryState.');
        }

        $bindings = [];
        $parts = array_filter([
            $this->compileColumns($state, $bindings),
            $this->compileFrom($state, $bindings),
            $this->compileJoins($state, $bindings),
            $this->compileWheres($state, $bindings),
            $this->compileGroups($state, $bindings),
            $this->compileHavings($state, $bindings),
            $this->compileOrders($state, $bindings),
            $this->compileLimitOffset($state),
            $this->compileUnions($state, $bindings),
            $this->compileLock($state),
        ], static fn (string $p): bool => $p !== '');

        return [implode(' ', $parts), $bindings];
    }

    /** @return array{0: string, 1: list<mixed>} */
    public function compileInsert(string $table, array $rows): array
    {
        if ($rows === []) {
            throw new \InvalidArgumentException('Cannot compile INSERT with no rows.');
        }

        $first = $rows[0];
        if (!is_array($first)) {
            // Single assoc row given as $rows directly.
            $rows  = [$rows];
            $first = $rows[0];
        }

        $columns  = array_keys($first);
        $quotedCs = implode(', ', array_map($this->quote(...), $columns));

        $bindings = [];
        $tuples   = [];
        foreach ($rows as $row) {
            $place = [];
            foreach ($columns as $col) {
                $place[]    = '?';
                $bindings[] = $row[$col] ?? null;
            }
            $tuples[] = '(' . implode(', ', $place) . ')';
        }

        $sql = 'INSERT INTO ' . $this->quote($table)
            . ' (' . $quotedCs . ') VALUES ' . implode(', ', $tuples);

        return [$sql, $bindings];
    }

    /** @return array{0: string, 1: list<mixed>} */
    public function compileUpdate(object $state, array $values): array
    {
        if (!$state instanceof QueryState) {
            throw new \InvalidArgumentException('compileUpdate requires QueryState.');
        }
        if ($state->from === null) {
            throw new \LogicException('UPDATE requires a FROM table.');
        }
        if ($values === []) {
            throw new \InvalidArgumentException('UPDATE requires at least one column to set.');
        }

        $bindings = [];
        $sets     = [];
        foreach ($values as $col => $val) {
            // Allow Raw / Node values for column = column + 1 style
            // expressions. Scalars/null go through as ? bindings.
            if ($val instanceof Node) {
                $sets[] = $this->quote((string) $col) . ' = ' . $this->compileNode($val, $bindings);
            } else {
                $sets[]     = $this->quote((string) $col) . ' = ?';
                $bindings[] = $val;
            }
        }

        $sql = 'UPDATE ' . $this->compileFromTarget($state->from, $bindings)
            . ' SET ' . implode(', ', $sets);

        $where = $this->compileWheres($state, $bindings);
        if ($where !== '') {
            $sql .= ' ' . $where;
        }

        return [$sql, $bindings];
    }

    /** @return array{0: string, 1: list<mixed>} */
    public function compileDelete(object $state): array
    {
        if (!$state instanceof QueryState) {
            throw new \InvalidArgumentException('compileDelete requires QueryState.');
        }
        if ($state->from === null) {
            throw new \LogicException('DELETE requires a FROM table.');
        }

        $bindings = [];
        $sql      = 'DELETE FROM ' . $this->compileFromTarget($state->from, $bindings);

        $where = $this->compileWheres($state, $bindings);
        if ($where !== '') {
            $sql .= ' ' . $where;
        }

        return [$sql, $bindings];
    }

    // ---------- compile pieces ----------

    /** @param list<mixed> $bindings */
    protected function compileColumns(QueryState $state, array &$bindings): string
    {
        if ($state->select === []) {
            $select = ['*'];
        } else {
            $select = [];
            foreach ($state->select as $node) {
                $select[] = $this->compileNode($node, $bindings);
            }
        }

        return ($state->distinct ? 'SELECT DISTINCT ' : 'SELECT ') . implode(', ', $select);
    }

    /** @param list<mixed> $bindings */
    protected function compileFrom(QueryState $state, array &$bindings): string
    {
        if ($state->from === null) {
            return '';
        }
        return 'FROM ' . $this->compileFromTarget($state->from, $bindings);
    }

    /** @param list<mixed> $bindings */
    private function compileFromTarget(Identifier|Subquery $from, array &$bindings): string
    {
        if ($from instanceof Subquery) {
            [$sub, $subBindings] = $this->compileSelect($from->state);
            foreach ($subBindings as $b) {
                $bindings[] = $b;
            }
            $alias = $from->alias ?? throw new \LogicException('Subquery in FROM requires an alias.');
            return '(' . $sub . ') AS ' . $this->quote($alias);
        }

        return $this->compileIdentifier($from);
    }

    /** @param list<mixed> $bindings */
    protected function compileJoins(QueryState $state, array &$bindings): string
    {
        if ($state->joins === []) {
            return '';
        }

        $out = [];
        foreach ($state->joins as $j) {
            $table = $j->table instanceof Subquery
                ? $this->compileFromTarget($j->table, $bindings)
                : $this->compileIdentifier($j->table);

            $on = $j->on !== null ? ' ON ' . $this->compileNode($j->on, $bindings) : '';
            $out[] = $j->kind . ' JOIN ' . $table . $on;
        }
        return implode(' ', $out);
    }

    /** @param list<mixed> $bindings */
    protected function compileWheres(QueryState $state, array &$bindings): string
    {
        if ($state->wheres === []) {
            return '';
        }
        return 'WHERE ' . $this->compileAndList($state->wheres, $bindings);
    }

    /** @param list<mixed> $bindings */
    protected function compileGroups(QueryState $state, array &$bindings): string
    {
        if ($state->groups === []) {
            return '';
        }
        $parts = [];
        foreach ($state->groups as $node) {
            $parts[] = $this->compileNode($node, $bindings);
        }
        return 'GROUP BY ' . implode(', ', $parts);
    }

    /** @param list<mixed> $bindings */
    protected function compileHavings(QueryState $state, array &$bindings): string
    {
        if ($state->havings === []) {
            return '';
        }
        return 'HAVING ' . $this->compileAndList($state->havings, $bindings);
    }

    /** @param list<mixed> $bindings */
    protected function compileOrders(QueryState $state, array &$bindings): string
    {
        if ($state->orders === []) {
            return '';
        }
        $parts = [];
        foreach ($state->orders as [$node, $dir]) {
            $parts[] = $this->compileNode($node, $bindings) . ' ' . $dir;
        }
        return 'ORDER BY ' . implode(', ', $parts);
    }

    protected function compileLimitOffset(QueryState $state): string
    {
        $sql = '';
        if ($state->limit !== null) {
            $sql .= 'LIMIT ' . $state->limit;
        }
        if ($state->offset !== null) {
            $sql .= ($sql === '' ? '' : ' ') . 'OFFSET ' . $state->offset;
        }
        return $sql;
    }

    /** @param list<mixed> $bindings */
    protected function compileUnions(QueryState $state, array &$bindings): string
    {
        if ($state->unions === []) {
            return '';
        }
        $parts = [];
        foreach ($state->unions as [$other, $all]) {
            [$sub, $subBindings] = $this->compileSelect($other);
            foreach ($subBindings as $b) {
                $bindings[] = $b;
            }
            $parts[] = ($all ? 'UNION ALL ' : 'UNION ') . $sub;
        }
        return implode(' ', $parts);
    }

    protected function compileLock(QueryState $state): string
    {
        return $state->lock ?? '';
    }

    // ---------- node compile ----------

    /** @param list<mixed> $bindings */
    protected function compileNode(Node $node, array &$bindings): string
    {
        return match (true) {
            $node instanceof Identifier => $this->compileIdentifier($node),
            $node instanceof Raw        => $this->compileRaw($node, $bindings),
            $node instanceof Binding    => $this->compileBinding($node, $bindings),
            $node instanceof Subquery   => $this->compileSubquery($node, $bindings),
            $node instanceof Expression => $this->compileExpression($node, $bindings),
            $node instanceof Join       => throw new \LogicException('Join nodes are compiled via compileJoins().'),
            default                     => throw new \LogicException('Unknown Node type: ' . $node::class),
        };
    }

    protected function compileIdentifier(Identifier $i): string
    {
        $sql = $this->quoteDotted($i->name);
        if ($i->alias !== null) {
            $sql .= ' AS ' . $this->quote($i->alias);
        }
        return $sql;
    }

    /** @param list<mixed> $bindings */
    protected function compileRaw(Raw $r, array &$bindings): string
    {
        foreach ($r->bindings as $b) {
            $bindings[] = $b;
        }
        return $r->sql;
    }

    /** @param list<mixed> $bindings */
    protected function compileBinding(Binding $b, array &$bindings): string
    {
        $bindings[] = $b->value;
        return '?';
    }

    /** @param list<mixed> $bindings */
    protected function compileSubquery(Subquery $s, array &$bindings): string
    {
        [$sub, $subBindings] = $this->compileSelect($s->state);
        foreach ($subBindings as $b) {
            $bindings[] = $b;
        }
        $sql = '(' . $sub . ')';
        if ($s->alias !== null) {
            $sql .= ' AS ' . $this->quote($s->alias);
        }
        return $sql;
    }

    /** @param list<mixed> $bindings */
    protected function compileExpression(Expression $e, array &$bindings): string
    {
        $op = strtoupper($e->op);

        return match ($op) {
            'AND', 'OR' => '(' . $this->compileLogicList($op, $e->operands, $bindings) . ')',
            'NOT'       => 'NOT (' . $this->compileNode($e->operands[0], $bindings) . ')',

            'IS NULL', 'IS NOT NULL' =>
                $this->compileNode($e->operands[0], $bindings) . ' ' . $op,

            'IN', 'NOT IN' => $this->compileIn($op, $e, $bindings),

            'BETWEEN', 'NOT BETWEEN' =>
                $this->compileNode($e->operands[0], $bindings) . ' ' . $op . ' '
                . $this->compileNode($e->operands[1], $bindings) . ' AND '
                . $this->compileNode($e->operands[2], $bindings),

            'EXISTS', 'NOT EXISTS' =>
                $op . ' ' . $this->compileNode($e->operands[0], $bindings),

            default => $this->compileBinaryOp($op, $e, $bindings),
        };
    }

    /**
     * @param list<mixed> $operands
     * @param list<mixed> $bindings
     */
    private function compileLogicList(string $op, array $operands, array &$bindings): string
    {
        $parts = [];
        foreach ($operands as $node) {
            $parts[] = $this->compileNode($node, $bindings);
        }
        return implode(' ' . $op . ' ', $parts);
    }

    /** @param list<mixed> $bindings */
    private function compileIn(string $op, Expression $e, array &$bindings): string
    {
        [$lhs, $values] = $e->operands;
        if ($values === []) {
            // SQL `x IN ()` is a syntax error. Encode the truth-value
            // directly so empty IN behaves the way callers expect.
            return $op === 'IN' ? '1 = 0' : '1 = 1';
        }
        $list = [];
        foreach ($values as $node) {
            $list[] = $this->compileNode($node, $bindings);
        }
        return $this->compileNode($lhs, $bindings) . ' ' . $op . ' (' . implode(', ', $list) . ')';
    }

    /** @param list<mixed> $bindings */
    private function compileBinaryOp(string $op, Expression $e, array &$bindings): string
    {
        [$lhs, $rhs] = $e->operands;
        return $this->compileNode($lhs, $bindings) . ' ' . $op . ' ' . $this->compileNode($rhs, $bindings);
    }

    /**
     * @param list<Node>  $list
     * @param list<mixed> $bindings
     */
    private function compileAndList(array $list, array &$bindings): string
    {
        $parts = [];
        foreach ($list as $node) {
            $parts[] = $this->compileNode($node, $bindings);
        }
        return implode(' AND ', $parts);
    }

    // ---------- identifier quoting ----------

    protected function quote(string $name): string
    {
        return $this->driver()->quoteIdentifier($name);
    }

    /**
     * Quote a dotted identifier ("users.email" → `"users"."email"`).
     * Bare "*" and trailing ".*" segments stay unquoted.
     */
    protected function quoteDotted(string $name): string
    {
        if ($name === '*') {
            return '*';
        }
        $parts = explode('.', $name);
        $out   = [];
        foreach ($parts as $seg) {
            $out[] = $seg === '*' ? '*' : $this->quote($seg);
        }
        return implode('.', $out);
    }
}

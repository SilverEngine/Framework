<?php
declare(strict_types=1);

namespace Silver\Orm\Query;

use Closure;
use PDO;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Query\Node\Binding;
use Silver\Orm\Query\Node\Expression;
use Silver\Orm\Query\Node\Identifier;
use Silver\Orm\Query\Node\Join;
use Silver\Orm\Query\Node\Node;
use Silver\Orm\Query\Node\Raw;
use Silver\Orm\Query\Node\Subquery;

/**
 * Fluent query builder. Accumulates a QueryState; terminals compile +
 * execute it. Model-aware behaviour (hydration, eager loading) lives
 * on a Builder subclass in P3 — this class is the SQL surface only.
 *
 * Every chainable method returns $this for a clean reading order.
 * Mutation happens in place; clone the builder explicitly when you
 * want to fork a constructed query.
 */
class Builder
{
    protected QueryState $state;

    public function __construct(
        protected readonly ConnectionManager $connections,
        protected readonly Compiler          $compiler,
        ?QueryState                          $state = null,
    ) {
        $this->state = $state ?? new QueryState();
    }

    public function state(): QueryState { return $this->state; }

    public function onConnection(string $name): static
    {
        $this->state->connection = $name;
        return $this;
    }

    // ---------- FROM / SELECT ----------

    public function from(string $table, ?string $alias = null): static
    {
        $this->state->from = new Identifier($table, $alias);
        return $this;
    }

    public function fromSub(Builder|Closure $sub, string $alias): static
    {
        $this->state->from = new Subquery($this->resolveSub($sub)->state, $alias);
        return $this;
    }

    /** @param string|list<string|Node> $columns */
    public function select(array|string $columns = ['*']): static
    {
        $cols = is_array($columns) ? $columns : [$columns];
        foreach ($cols as $c) {
            $this->state->select[] = $c instanceof Node ? $c : $this->identifierFromExpression($c);
        }
        return $this;
    }

    public function addSelect(string|Node $column): static
    {
        $this->state->select[] = $column instanceof Node
            ? $column
            : $this->identifierFromExpression($column);
        return $this;
    }

    /**
     * Accepts "col" or "col AS alias" (case-insensitive). Dotted names
     * like "users.email" keep working — they're handled inside the
     * grammar's identifier quoter.
     */
    private function identifierFromExpression(string $expr): Identifier
    {
        if (preg_match('/^(.+?)\s+AS\s+(.+)$/i', $expr, $m) === 1) {
            return new Identifier(trim($m[1]), trim($m[2]));
        }
        return new Identifier($expr);
    }

    public function distinct(bool $on = true): static
    {
        $this->state->distinct = $on;
        return $this;
    }

    // ---------- WHERE ----------

    /**
     * Three call shapes:
     *   ->where(Closure)                                — nested AND group
     *   ->where('col', $value)                          — `col = ?`
     *   ->where('col', '<', $value) / 'like' / etc      — explicit op
     */
    public function where(string|Closure $column, mixed $op = null, mixed $value = null): static
    {
        $this->state->wheres[] = $this->buildWhere($column, $op, $value);
        return $this;
    }

    public function orWhere(string|Closure $column, mixed $op = null, mixed $value = null): static
    {
        $next = $this->buildWhere($column, $op, $value);
        $prev = array_pop($this->state->wheres);
        if ($prev === null) {
            $this->state->wheres[] = $next;
            return $this;
        }
        // OR composes left-associatively: (prev OR next).
        if ($prev instanceof Expression && $prev->op === 'OR') {
            $this->state->wheres[] = new Expression('OR', [...$prev->operands, $next]);
        } else {
            $this->state->wheres[] = new Expression('OR', [$prev, $next]);
        }
        return $this;
    }

    /** @param list<mixed> $values */
    public function whereIn(string $column, array $values): static
    {
        $this->state->wheres[] = new Expression('IN', [
            new Identifier($column),
            array_map(static fn ($v): Binding => new Binding($v), $values),
        ]);
        return $this;
    }

    /** @param list<mixed> $values */
    public function whereNotIn(string $column, array $values): static
    {
        $this->state->wheres[] = new Expression('NOT IN', [
            new Identifier($column),
            array_map(static fn ($v): Binding => new Binding($v), $values),
        ]);
        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->state->wheres[] = new Expression('IS NULL', [new Identifier($column)]);
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->state->wheres[] = new Expression('IS NOT NULL', [new Identifier($column)]);
        return $this;
    }

    public function whereBetween(string $column, mixed $low, mixed $high): static
    {
        $this->state->wheres[] = new Expression('BETWEEN', [
            new Identifier($column), new Binding($low), new Binding($high),
        ]);
        return $this;
    }

    public function whereColumn(string $a, string $op, ?string $b = null): static
    {
        if ($b === null) {
            $b  = $op;
            $op = '=';
        }
        $this->state->wheres[] = new Expression($op, [new Identifier($a), new Identifier($b)]);
        return $this;
    }

    public function whereExists(Closure $cb): static
    {
        $sub = $this->newQuery();
        $cb($sub);
        $this->state->wheres[] = new Expression('EXISTS', [new Subquery($sub->state)]);
        return $this;
    }

    public function whereNotExists(Closure $cb): static
    {
        $sub = $this->newQuery();
        $cb($sub);
        $this->state->wheres[] = new Expression('NOT EXISTS', [new Subquery($sub->state)]);
        return $this;
    }

    public function whereRaw(string $sql, array $bindings = []): static
    {
        $this->state->wheres[] = new Raw($sql, $bindings);
        return $this;
    }

    // ---------- JOIN ----------

    public function join(string $table, string $first, string $op, string $second, string $kind = Join::KIND_INNER): static
    {
        $on = new Expression($op, [new Identifier($first), new Identifier($second)]);
        $this->state->joins[] = new Join($kind, new Identifier($table), $on);
        return $this;
    }

    public function leftJoin(string $table, string $first, string $op, string $second): static
    {
        return $this->join($table, $first, $op, $second, Join::KIND_LEFT);
    }

    public function rightJoin(string $table, string $first, string $op, string $second): static
    {
        return $this->join($table, $first, $op, $second, Join::KIND_RIGHT);
    }

    public function crossJoin(string $table): static
    {
        $this->state->joins[] = new Join(Join::KIND_CROSS, new Identifier($table), null);
        return $this;
    }

    // ---------- GROUP / ORDER / LIMIT ----------

    /** @param string|list<string> $columns */
    public function groupBy(array|string $columns): static
    {
        foreach ((array) $columns as $c) {
            $this->state->groups[] = new Identifier($c);
        }
        return $this;
    }

    public function having(string $column, string $op, mixed $value): static
    {
        $this->state->havings[] = new Expression($op, [new Identifier($column), new Binding($value)]);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $dir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        $this->state->orders[] = [new Identifier($column), $dir];
        return $this;
    }

    public function latest(string $column = 'created_at'): static { return $this->orderBy($column, 'desc'); }
    public function oldest(string $column = 'created_at'): static { return $this->orderBy($column, 'asc'); }

    public function limit(int $count): static
    {
        $this->state->limit = max(0, $count);
        return $this;
    }

    public function offset(int $count): static
    {
        $this->state->offset = max(0, $count);
        return $this;
    }

    // ---------- compile + run ----------

    /** @return array{0: string, 1: list<mixed>} */
    public function toSqlAndBindings(): array
    {
        return $this->compiler->for($this->state->connection)->compileSelect($this->state);
    }

    public function toSql(): string
    {
        return $this->toSqlAndBindings()[0];
    }

    /** @return list<mixed> */
    public function getBindings(): array
    {
        return $this->toSqlAndBindings()[1];
    }

    /**
     * Base shape returns assoc rows; subclasses (ModelBuilder) narrow
     * to a list of hydrated instances via covariant override.
     *
     * @return array<int, array<string, mixed>|object>
     */
    public function get(): array
    {
        [$sql, $bindings] = $this->toSqlAndBindings();
        $stmt = $this->connections->raw($sql, $bindings, $this->state->connection);
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    /** @return array<int, array<string, mixed>|object> */
    public function all(): array { return $this->get(); }

    /**
     * Returns a single row. Base Builder yields an assoc array
     * (?array); ModelBuilder narrows the phpdoc to `?T` but PHP's
     * declared return type stays `mixed` to allow subclass override
     * without a covariance complaint.
     *
     * @return array<string, mixed>|object|null
     */
    public function first(): mixed
    {
        $rows = (clone $this)->limit(1)->get();
        return $rows[0] ?? null;
    }

    /** @return array<string, mixed>|object|null */
    public function find(mixed $id, string $pk = 'id'): mixed
    {
        return (clone $this)->where($pk, $id)->first();
    }

    public function count(string $column = '*'): int
    {
        return (int) $this->aggregate('COUNT', $column);
    }

    /**
     * Project a single column out as a flat list. When $key is given,
     * the result is keyed by that column (handy for id→name lookups).
     *
     * @return array<int|string, mixed>
     */
    public function pluck(string $column, ?string $key = null): array
    {
        $clone = clone $this;
        $cols  = [$column];
        if ($key !== null) {
            $cols[] = $key;
        }
        $clone->state->select = [];
        $clone->select($cols);

        $rows = $clone->get();
        $out  = [];
        foreach ($rows as $row) {
            $value = is_array($row) ? ($row[$column] ?? null) : ($row->{$column} ?? null);
            if ($key !== null) {
                $k = is_array($row) ? ($row[$key] ?? null) : ($row->{$key} ?? null);
                $out[(string) $k] = $value;
            } else {
                $out[] = $value;
            }
        }
        return $out;
    }

    public function sum(string $column): int|float
    {
        $v = $this->aggregate('SUM', $column);
        return $v === null ? 0 : $v + 0;
    }

    public function avg(string $column): float
    {
        return (float) $this->aggregate('AVG', $column);
    }

    public function min(string $column): mixed { return $this->aggregate('MIN', $column); }
    public function max(string $column): mixed { return $this->aggregate('MAX', $column); }

    public function exists(): bool
    {
        return (clone $this)->limit(1)->first() !== null;
    }

    public function doesntExist(): bool { return !$this->exists(); }

    /** @param array<string, mixed>|list<array<string, mixed>> $values */
    public function insert(array $values): int
    {
        $table = $this->requireTableName('INSERT');
        $rows  = $this->isAssoc($values) ? [$values] : array_values($values);
        [$sql, $bindings] = $this->compiler->for($this->state->connection)
            ->compileInsert($table, $rows);
        $this->connections->raw($sql, $bindings, $this->state->connection);
        return count($rows);
    }

    public function insertGetId(array $values): string
    {
        $this->insert($values);
        return $this->connections->lastInsertId($this->state->connection);
    }

    /** @param array<string, mixed> $values */
    public function update(array $values): int
    {
        [$sql, $bindings] = $this->compiler->for($this->state->connection)
            ->compileUpdate($this->state, $values);
        return $this->connections->raw($sql, $bindings, $this->state->connection)->rowCount();
    }

    public function delete(): int
    {
        [$sql, $bindings] = $this->compiler->for($this->state->connection)
            ->compileDelete($this->state);
        return $this->connections->raw($sql, $bindings, $this->state->connection)->rowCount();
    }

    /** @param Closure(array<string, mixed>): mixed $fn */
    public function each(int $chunkSize, Closure $fn): void
    {
        if ($chunkSize < 1) {
            throw new \InvalidArgumentException('Chunk size must be ≥ 1.');
        }
        $page = 0;
        while (true) {
            $rows = (clone $this)->limit($chunkSize)->offset($page * $chunkSize)->get();
            if ($rows === []) {
                return;
            }
            foreach ($rows as $row) {
                $fn($row);
            }
            if (count($rows) < $chunkSize) {
                return;
            }
            $page++;
        }
    }

    /**
     * Fresh empty query targeting the same connection / compiler.
     * Returns a base Builder; subclasses with incompatible constructor
     * signatures (like ModelBuilder) must override this to return
     * their own type.
     */
    public function newQuery(): self
    {
        return new self($this->connections, $this->compiler);
    }

    // ---------- introspection ----------

    /**
     * Returns a QueryPlan describing what the database WOULD do for
     * this select. Side-effect-free.
     */
    public function explain(): QueryPlan
    {
        return $this->plan(execute: false);
    }

    /**
     * Like explain(), but actually executes the query so the plan
     * carries real row counts + timing. For SELECTs the result is
     * thrown away; for UPDATE/DELETE you must use the dedicated
     * analyzeUpdate()/analyzeDelete() so we know to wrap in a
     * savepoint (P5 work — sqlite analyze() is read-only).
     */
    public function analyze(): QueryPlan
    {
        return $this->plan(execute: true);
    }

    public function explainCount(string $column = '*'): QueryPlan
    {
        return $this->explainAggregate('COUNT', $column);
    }

    public function explainSum(string $column): QueryPlan { return $this->explainAggregate('SUM', $column); }
    public function explainAvg(string $column): QueryPlan { return $this->explainAggregate('AVG', $column); }
    public function explainMin(string $column): QueryPlan { return $this->explainAggregate('MIN', $column); }
    public function explainMax(string $column): QueryPlan { return $this->explainAggregate('MAX', $column); }

    public function explainAggregate(string $fn, string $column): QueryPlan
    {
        $clone = clone $this;
        $clone->state->select = [
            new Raw(strtoupper($fn) . '(' . ($column === '*' ? '*' : $this->quoteForGrammar($column)) . ')'),
        ];
        $clone->state->orders = [];
        $clone->state->limit  = null;
        $clone->state->offset = null;
        return $clone->explain();
    }

    // ---------- internals ----------

    private function plan(bool $execute): QueryPlan
    {
        [$sql, $bindings] = $this->toSqlAndBindings();
        $grammar = $this->compiler->for($this->state->connection);
        $prefix  = $execute ? $grammar->analyzePrefix() : $grammar->explainPrefix();
        $wrapped = $prefix . $sql;

        $start = $execute ? hrtime(true) : null;
        $stmt  = $this->connections->raw($wrapped, $bindings, $this->state->connection);
        $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $ms    = $start !== null ? (hrtime(true) - $start) / 1_000_000 : null;

        return new QueryPlan(
            sql:         $wrapped,
            originalSql: $sql,
            bindings:    $bindings,
            driver:      $grammar->driver(),
            rows:        $rows,
            totalMs:     $ms,
            formatted:   PlanFormatter::format($sql, $rows, $ms),
        );
    }

    private function aggregate(string $fn, string $column): mixed
    {
        $clone = clone $this;
        $clone->state->select = [
            new Raw($fn . '(' . ($column === '*' ? '*' : $this->quoteForGrammar($column)) . ')'),
        ];
        $clone->state->orders = [];
        $clone->state->limit  = null;
        $clone->state->offset = null;

        [$sql, $bindings] = $clone->toSqlAndBindings();
        $stmt = $this->connections->raw($sql, $bindings, $this->state->connection);
        return $stmt->fetchColumn();
    }

    private function quoteForGrammar(string $column): string
    {
        // Bounce through the grammar so aggregates respect the active
        // driver's identifier quoting. Cheap — grammars are tiny.
        return $this->compiler->for($this->state->connection)
            ->driver()
            ->quoteIdentifier($column);
    }

    private function requireTableName(string $op): string
    {
        if (!($this->state->from instanceof Identifier)) {
            throw new \LogicException("{$op} requires from() with a plain table name.");
        }
        return $this->state->from->name;
    }

    private function isAssoc(array $a): bool
    {
        if ($a === []) {
            return true;
        }
        return !array_is_list($a);
    }

    private function buildWhere(string|Closure $column, mixed $op, mixed $value): Node
    {
        if ($column instanceof Closure) {
            $sub = $this->newQuery();
            $column($sub);
            $children = $sub->state->wheres;
            return match (count($children)) {
                0       => new Raw('1 = 1'),
                1       => $children[0],
                default => new Expression('AND', $children),
            };
        }

        // ->where('col', $value) shorthand.
        if ($value === null && $op !== null && !$this->looksLikeOp($op)) {
            $value = $op;
            $op    = '=';
        } elseif ($op === null) {
            throw new \InvalidArgumentException("where('{$column}', …) requires a value.");
        }

        return new Expression((string) $op, [new Identifier($column), new Binding($value)]);
    }

    private function looksLikeOp(mixed $candidate): bool
    {
        if (!is_string($candidate)) {
            return false;
        }
        $ops = ['=', '<>', '!=', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE', 'ILIKE', 'IS', 'IS NOT'];
        return in_array(strtoupper($candidate), $ops, true);
    }

    private function resolveSub(Builder|Closure $sub): Builder
    {
        if ($sub instanceof Builder) {
            return $sub;
        }
        $q = $this->newQuery();
        $sub($q);
        return $q;
    }
}

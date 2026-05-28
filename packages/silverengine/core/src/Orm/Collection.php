<?php
declare(strict_types=1);

namespace Silver\Orm;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * Typed iterable result wrapper. Not a re-implementation of Laravel
 * Collection's 200 methods — just the intersection that actually
 * gets used. Add more on demand.
 *
 * @template T
 * @implements ArrayAccess<int|string, T>
 * @implements IteratorAggregate<int|string, T>
 * @phpstan-consistent-constructor
 */
class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /** @param array<int|string, T> $items */
    public function __construct(protected array $items = []) {}

    /** @param iterable<int|string, T> $items */
    public static function make(iterable $items = []): static
    {
        $arr = $items instanceof Traversable ? iterator_to_array($items) : $items;
        return new static($arr);
    }

    /** @return array<int|string, T> */
    public function all(): array { return $this->items; }

    public function isEmpty(): bool { return $this->items === []; }
    public function isNotEmpty(): bool { return $this->items !== []; }

    /**
     * @param  null|callable(T, int|string): bool $fn
     * @return T|null
     */
    public function first(?callable $fn = null): mixed
    {
        if ($fn === null) {
            foreach ($this->items as $v) {
                return $v;
            }
            return null;
        }
        foreach ($this->items as $k => $v) {
            if ($fn($v, $k)) {
                return $v;
            }
        }
        return null;
    }

    /** @return T|null */
    public function last(): mixed
    {
        if ($this->items === []) {
            return null;
        }
        return $this->items[array_key_last($this->items)];
    }

    /**
     * @param callable(T, int|string): mixed $fn
     * @return static<mixed>
     */
    public function map(callable $fn): static
    {
        $out = [];
        foreach ($this->items as $k => $v) {
            $out[$k] = $fn($v, $k);
        }
        return new static($out);
    }

    /**
     * @param callable(T, int|string): bool $fn
     * @return static<T>
     */
    public function filter(callable $fn): static
    {
        $out = [];
        foreach ($this->items as $k => $v) {
            if ($fn($v, $k)) {
                $out[$k] = $v;
            }
        }
        return new static($out);
    }

    /** @param callable(T, int|string): void $fn */
    public function each(callable $fn): static
    {
        foreach ($this->items as $k => $v) {
            $fn($v, $k);
        }
        return $this;
    }

    /**
     * @param string|callable(T): mixed $key
     * @return static<mixed>
     */
    public function pluck(string|callable $key, ?string $indexBy = null): static
    {
        $out = [];
        $extract = is_callable($key) ? $key : fn ($item): mixed => $this->dig($item, $key);
        foreach ($this->items as $i => $item) {
            $value = $extract($item);
            if ($indexBy !== null) {
                $k = $this->dig($item, $indexBy);
                $out[(string) $k] = $value;
            } else {
                $out[$i] = $value;
            }
        }
        return new static($out);
    }

    /**
     * @param string|callable(T): mixed $key
     * @return static<T>
     */
    public function keyBy(string|callable $key): static
    {
        $extract = is_callable($key) ? $key : fn ($item): mixed => $this->dig($item, $key);
        $out = [];
        foreach ($this->items as $item) {
            $out[(string) $extract($item)] = $item;
        }
        return new static($out);
    }

    /**
     * @param string|callable(T): mixed $key
     * @return static<static<T>>
     */
    public function groupBy(string|callable $key): static
    {
        $extract = is_callable($key) ? $key : fn ($item): mixed => $this->dig($item, $key);
        $groups = [];
        foreach ($this->items as $item) {
            $k = (string) $extract($item);
            $groups[$k] ??= [];
            $groups[$k][] = $item;
        }
        return new static(array_map(fn ($g): static => new static($g), $groups));
    }

    /**
     * @param callable(T, int|string): bool $fn
     * @return array{0: static<T>, 1: static<T>} [pass, fail]
     */
    public function partition(callable $fn): array
    {
        $pass = $fail = [];
        foreach ($this->items as $k => $v) {
            if ($fn($v, $k)) { $pass[$k] = $v; } else { $fail[$k] = $v; }
        }
        return [new static($pass), new static($fail)];
    }

    /** @return static<T> */
    public function values(): static { return new static(array_values($this->items)); }

    /** @return static<T> */
    public function reverse(): static { return new static(array_reverse($this->items, true)); }

    /** @return static<T> */
    public function take(int $count): static
    {
        return new static($count < 0 ? array_slice($this->items, $count) : array_slice($this->items, 0, $count));
    }

    /**
     * Split into N-sized chunks. Returns a Collection of non-empty
     * lists; wrap each chunk in a Collection yourself if you need that
     * (`->chunk(10)->map(fn ($c) => new Collection($c))`).
     *
     * @return static<non-empty-list<T>>
     */
    public function chunk(int $size): static
    {
        return new static(array_chunk($this->items, $size));
    }

    /**
     * @param string|callable(T): mixed $by
     */
    public function sum(string|callable $by): int|float
    {
        $extract = is_callable($by) ? $by : fn ($i): mixed => $this->dig($i, $by);
        $sum = 0;
        foreach ($this->items as $item) {
            $sum += (float) $extract($item);
        }
        return $sum;
    }

    /**
     * @param list<int|string> $keys
     * @return static<T>
     */
    public function only(array $keys): static
    {
        $flip = array_flip($keys);
        return new static(array_intersect_key($this->items, $flip));
    }

    /**
     * @param list<int|string> $keys
     * @return static<T>
     */
    public function except(array $keys): static
    {
        $flip = array_flip($keys);
        return new static(array_diff_key($this->items, $flip));
    }

    /** Convert a list of models / structures to plain arrays. */
    public function toArray(): array
    {
        $out = [];
        foreach ($this->items as $k => $v) {
            $out[$k] = is_object($v) && method_exists($v, 'toArray') ? $v->toArray() : $v;
        }
        return $out;
    }

    public function jsonSerialize(): array
    {
        $out = [];
        foreach ($this->items as $k => $v) {
            $out[$k] = $v instanceof JsonSerializable ? $v->jsonSerialize() : $v;
        }
        return $out;
    }

    public function count(): int { return count($this->items); }

    /** @return ArrayIterator<int|string, T> */
    public function getIterator(): ArrayIterator { return new ArrayIterator($this->items); }

    public function offsetExists(mixed $offset): bool       { return array_key_exists($offset, $this->items); }
    public function offsetGet(mixed $offset): mixed         { return $this->items[$offset] ?? null; }
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) { $this->items[] = $value; } else { $this->items[$offset] = $value; }
    }
    public function offsetUnset(mixed $offset): void        { unset($this->items[$offset]); }

    // ---------- helpers ----------

    private function dig(mixed $item, string $path): mixed
    {
        if ($path === '') {
            return $item;
        }
        // Dotted access for models/arrays. "team.name" → $item->team->name or $item['team']['name'].
        $segments = explode('.', $path);
        $cursor   = $item;
        foreach ($segments as $seg) {
            if (is_array($cursor)) {
                $cursor = $cursor[$seg] ?? null;
            } elseif (is_object($cursor)) {
                $cursor = $cursor->{$seg} ?? null;
            } else {
                return null;
            }
        }
        return $cursor;
    }
}

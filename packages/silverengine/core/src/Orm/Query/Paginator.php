<?php
declare(strict_types=1);

namespace Silver\Orm\Query;

/**
 * Result page from {@see Builder::paginate()}. JSON shape (snake_case
 * meta, `data` for items) drops straight into an API response or a
 * Wisp page prop.
 *
 * @template T
 * @implements \IteratorAggregate<int, T>
 */
final readonly class Paginator implements \Countable, \IteratorAggregate, \JsonSerializable
{
    public int $lastPage;

    /** @param list<T> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $perPage,
        public int $currentPage,
    ) {
        $this->lastPage = max(1, (int) ceil($total / max(1, $perPage)));
    }

    /** @return list<T> */
    public function items(): array
    {
        return $this->items;
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function onFirstPage(): bool
    {
        return $this->currentPage <= 1;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    /** @return array{data: list<T>, total: int, per_page: int, current_page: int, last_page: int} */
    public function toArray(): array
    {
        return [
            'data'         => $this->items,
            'total'        => $this->total,
            'per_page'     => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page'    => $this->lastPage,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

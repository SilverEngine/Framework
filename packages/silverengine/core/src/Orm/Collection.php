<?php
declare(strict_types=1);

namespace Silver\Orm;

/**
 * ORM result collection. The implementation lives in
 * {@see \Silver\Support\Collection} (promoted for general use); this
 * subclass keeps every existing `Silver\Orm\Collection` type hint and
 * `instanceof` working. Methods return `static`, so chains on an ORM
 * collection stay ORM collections.
 *
 * @template T
 * @extends \Silver\Support\Collection<T>
 */
class Collection extends \Silver\Support\Collection
{
}

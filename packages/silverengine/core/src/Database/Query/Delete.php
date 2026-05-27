<?php
declare(strict_types=1);

namespace Silver\Database\Query;

use Silver\Database\Query;
use Silver\Database\Traits\QueryColumns;
use Silver\Database\Traits\QueryFrom;
use Silver\Database\Traits\QueryJoin;
use Silver\Database\Traits\QueryWH;
use Silver\Database\Traits\QueryOrder;
use Silver\Database\Traits\QueryLimit;

/**
 * `DELETE` query builder. Standard SQL DELETE has no GROUP BY / HAVING
 * clauses, so the corresponding traits (and their `// FIXME: remove`-
 * marked compile calls) are intentionally absent — emitting them would
 * have produced invalid SQL on every driver.
 */
class Delete extends Query
{
    use QueryColumns, QueryFrom, QueryJoin, QueryWH, QueryOrder, QueryLimit;

    public function __construct($columns = [])
    {
        $this->setColumns($columns);
    }

    protected static function compile($q)
    {
        return 'DELETE'
            . static::compileColumns($q)
            . static::compileFrom($q)
            . static::compileJoin($q)
            . static::compileWhere($q)
            . static::compileOrder($q)
            . static::compileLimit($q);
    }
}

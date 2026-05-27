<?php
declare(strict_types=1);

namespace Silver\Database\Traits;

use Silver\Database\Parts\Filter;
use Silver\Database\Parts\Paren;
use Silver\Database\Parts\Parts;
use Silver\Database\Parts\Value;
use Silver\Database\Parts\Literal;
use Silver\Database\Query;

trait QueryWH
{

    private $where = [];
    private $having = [];

    public function where($column, $operator=null, $value=null, $how='and', $not=false) 
    {
        return $this->cond('where', $column, $operator, $value, $how, $not);
    }

    public function having($column, $operator=null, $value=null, $how='and', $not=false) 
    {
        return $this->cond('having', $column, $operator, $value, $how, $not);
    }

    private function cond($cond, $column, $operator, $value, $how, $not) 
    {
        $how = strtoupper($how);
        if(!($how == 'AND' || $how == 'OR')) {
            throw new \Exception("Unknown boolean operator '$how'");
        }

        if(is_callable($column)) {
            $pfn = $cond . 'Paren';
            return $this->$pfn($column, $how, $not);
        }

        list($operator, $value) = $this->prepareOperatorValue($operator, $value);

        if(is_callable($value)) {
            $value = $value();
        }

        if($operator == 'BETWEEN') {
            // Value must be [1, 2] array
            list($from, $to) = $value;
            $filter = new Filter(
                $column,
                $operator,
                new Parts(Value::ensure($from), 'AND', Value::ensure($to)),
                $not
            );
        } else {
            $filter = new Filter($column, $operator, $value, $not);
        }

        /**
 * XXX 
         * Postgresql support doesn't support referencing to
         * agregate columns in having.
         */
        // For PostgreSQL
        // $filter = static::processFilter($cond, $filter)
        /*
         * if(having) {
         *   $filter->col = Query::current?()->getColumn($filter->col)->definition
         * }
         */

        if($this->$cond) {
            $this->$cond = new Parts($this->$cond, $how, $filter);
        } else {
            $this->$cond = $filter;
        }

        return $this;
    }

    private function prepareOperatorValue($operator, $value) 
    {
        if($value === null) {
            if($operator instanceof Query || is_array($operator)) {
                return ['IN', $operator];
            }
            if(is_bool($operator)) {
                return ['=', Literal::ensure($operator)];
            }
            if($operator === null) {
                return ['IS', Literal::null()];
            }
            return ['=', $operator];
        } else {
            return [strtoupper($operator), $value];
        }
    }

    private function havingParen($cb, $how = 'and', $not = false)
    {
        return $this->paren('having', $cb, $how, $not);
    }

    private function whereParen($cb, $how = 'and', $not = false)
    {
        return $this->paren('where', $cb, $how, $not);
    }

    /**
     * Wrap a grouped sub-clause in parens. Used when the caller hands a
     * Closure to ->where() / ->having() to build a nested boolean group:
     *
     *     ->where(fn($q) => $q->where('a', 1)->orWhere('b', 2))
     *
     * The previous implementation shadowed `$cond` (which held the
     * 'where'/'having' string) with `$this->$cond` (the existing filter
     * value) on the very first line, breaking every subsequent
     * `$this->$cond = …` write. Reworked to keep the slot name and the
     * existing value in separate variables.
     */
    private function paren($condName, $cb, $how, $not)
    {
        $existing = $this->$condName;

        // Reset the slot so the callback writes into a clean group.
        $this->$condName = null;
        $cb($this);
        $group = $this->$condName;

        // Empty callback — restore and bail out untouched.
        if ($group === null) {
            $this->$condName = $existing;
            return $this;
        }

        $wrapped = new Paren($group);
        $node    = $not ? new Parts('NOT', $wrapped) : $wrapped;

        $this->$condName = $existing === null
            ? $node
            : new Parts($existing, $how, $node);

        return $this;
    }

    public function __call($str, $args) 
    {
        $orig = $str;

        $cond = null;
        $column = null;
        $operator = null;
        $value = null;
        $how = 'and';
        $not = false;

        if(stripos($str, 'or') === 0) {
            $str = substr($str, 2);
            $how = 'or';
        }

        if(stripos($str, 'not') === 0) {
            $str = substr($str, 3);
            $not = true;
        }

        if(stripos($str, 'where') === 0) {
            $str = substr($str, 5);
            $cond = 'where';
        } elseif(stripos($str, 'having') === 0) {
            $str = substr($str, 6);
            $cond = 'having';
        }

        if(strlen($str) > 0) {
            $column = self::snake_case($str);
        }

        if($cond) {
            if(!$column) { $column = array_shift($args);
            }
            $operator = array_shift($args);
            $value = array_shift($args);

            return $this->$cond($column, $operator, $value, $how, $not);
        } else {
            throw new \Exception('Undefined method ' . static::class . '::' . $orig);
        }
    }

    public static function compileWhere($q) 
    {
        if($q->where) {
            return ' WHERE ' . $q->where;
        }
        return '';
    }

    public static function compileHaving($q) 
    {
        if($q->having) {
            return ' HAVING ' . $q->having;
        }
        return '';
    }

    private static function snake_case($str) 
    {
        $out = '';
        $str = lcfirst($str);
        for($i=0; $i < strlen($str); $i++) {
            $chr = $str[$i];
            if ('A' <= $chr && $chr <= 'Z') {
                $out .= '_' . strtolower($chr);
            } else {
                $out .= $chr;
            }
        }
        return $out;
    }
}
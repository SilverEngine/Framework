<?php
declare(strict_types=1);

namespace Silver\Database;

use Silver\Database\Query\Drop;
use Silver\Database\Parts\Fnx;
use Silver\Database\Parts\Column;

abstract class Query extends Db
{
    private $bindings = [];
    private $sources = [];

    use Compiler;

    /** @param array ...$columns */
    public static function select(...$columns)
    {
        return self::instance('select', [$columns]);
    }

    /** @param string $column */
    public static function count($column = 'count')
    {
        return self::select(
            Column::ensure(
                [
                null,
                Fnx::count(),
                $column
                ]
            )
        );
    }

    /** @param array ...$columns */
    public static function delete(...$columns)
    {
        return self::instance('delete', [$columns]);
    }

    /** @param array $updates */
    public static function update($table, $updates = [])
    {
        return self::instance('update', [$table, $updates]);
    }

    public static function insert($table, $data = null)
    {
        return self::instance('insert', [$table, $data]);
    }

    public static function create($table, $cb)
    {
        return self::instance('create', [$table, $cb]);
    }

    public static function drop($table)
    {
        return self::instance('drop', [$table]);
    }

    public static function alter($table, $cb = null)
    {
        return self::instance('alter', [$table, $cb]);
    }

    /** @param array $args */
    protected static function instance($type, $args = [])
    {
        $class = 'Silver\\Database\\Query\\' . ucfirst($type);
        return new $class(...$args);
    }

    public function bind($value)
    {
        if(is_array($value)) {
            $this->bindings = array_merge($this->bindings, $value);
        } else {
            $this->bindings[] = $value;
        }
    }

    /** @return array */
    public function getBindings()
    {
        return $this->bindings;
    }

    public function clearBindings()
    {
        $this->bindings = [];
    }

    public function addSource($source) 
    {
        $this->sources[$source->name()] = $source;
    }

    public function getSource($name) 
    {
        if (isset($this->sources[$name])) {
            return $this->sources[$name];
        }
        return null;
    }

    public function getSourceByModel($class) 
    {
        foreach($this->sources as $source) {
            if ($source instanceof \Silver\Database\Source\Model) {
                if ($source->model() == $class) {
                    return $source;
                }
            }
        }
        return null;
    }
}

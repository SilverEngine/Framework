<?php
declare(strict_types=1);

namespace Silver\Database;

class Model extends QueryObject
{

    public static function query() 
    {
        return Query::select()
            ->from(static::class)
            ->setFetchStyle(static::class);
    }

    public static function where($column, $op = null, $value = null) 
    {
        return static::query()
            ->where($column, $op, $value);
    }

    public static function find($id) 
    {
        return static::where(static::primaryKey(), $id)
            ->first();
    }

    public function getFilterable()
    {
        return $this->filterable;
    }

    public function getIncludable()
    {
        return $this->includable;
    }

    public function getSearchable()
    {
        return $this->searchable;
    }

    public function getHidden()
    {
        return $this->hidden;
    }

    public function getFillable()
    {
        return $this->fillable;
    }

    public function getSelectable()
    {
        return $this->selectable;
    }

    public static function all() 
    {
        return static::query()->all();
    }

    public static function create($data) 
    {
        Query::insert(static::class, $data)->execute();
        return static::find(Query::lastInsertId());
    }

    public function delete() 
    {
        Query::delete()
            ->from(static::class)
            ->where(static::primaryKey(), $this->id)
            ->execute();
    }

    public function save() 
    {
        $id = static::primaryKey();

        if (isset($this->$id)) {
            $dirty = $this->dirtyData();

            if (count($dirty) > 0) {
                $q = Query::update(static::class)->where(static::primaryKey(), $this->$id);
            
                foreach ($dirty as $key => $val) {
                    $q->set($key, $val);
                }
                $q->execute();
            }
            return $this;
        } else {
            Query::insert(static::class, $this->data())->execute();
            Query::select()
                ->from(static::class)
                ->where(static::primaryKey(), Query::lastInsertId())
                ->first($this);
            return $this;
        }
    }
}
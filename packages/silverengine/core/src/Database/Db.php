<?php
declare(strict_types=1);

namespace Silver\Database;

use \PDO;

/**
 * Db is now a thin facade over the extracted managers — it preserves
 * every legacy static entry point (`Db::`/`Query::`/`Model::`) by
 * delegating:
 *
 *  - connection registry / raw / quote / exec → {@see ConnectionManager}
 *  - nested transactions / counter           → {@see TransactionManager}
 *
 * What stays here is the per-instance side that `Query` (extends Db)
 * genuinely needs: the executed statement, fetch style, debug flags and
 * the result-fetching/transform machinery. Behaviour is unchanged.
 */
abstract class Db
{
    private static $global_debug = false;
    private $debug = null;
    private $query = null;

    private $fetch_style = PDO::FETCH_OBJ;

    /** Resolve the ConnectionManager singleton through the app container. */
    private static function cm(): ConnectionManager
    {
        return app(ConnectionManager::class);
    }

    /** Resolve the TransactionManager singleton through the app container. */
    private static function tm(): TransactionManager
    {
        return app(TransactionManager::class);
    }

    abstract public function toSql();

    // Optional virtual methods, used by ->first()
    public function getLimit()
    {
        throw new \Exception('Unable to get limit on ' . static::class);
    }
    public function limit($count)
    {
        throw new \Exception('Unable to set limit for ' . static::class);
    }

    public static function connect($name, $dsn, $username = null, $password = null): void
    {
        self::cm()->connect($name, $dsn, $username, $password);
    }

    /** @param bool $enabled */
    public static function debugMode($enabled = true): void
    {
        self::$global_debug = $enabled;
    }

    /** @param bool $enabled */
    public function debug($enabled = true): static
    {
        $this->debug = $enabled;

        return $this;
    }

    /** @return bool|null */
    public function isDebug()
    {
        if (isset($this) && $this instanceof Db && $this->debug !== null) {
            return $this->debug;
        }

        return self::$global_debug;
    }

    /** @throws \Exception */
    public static function setConnection($name): void
    {
        self::cm()->setDefault($name);
    }

    public static function withConnection($name, $cb): void
    {
        self::cm()->withConnection($name, $cb);
    }

    public static function connections(): array
    {
        return self::cm()->names();
    }

    /** @throws \Exception */
    public static function connection($name = null): PDO
    {
        return self::cm()->pdo($name);
    }

    /**
     * @return string|int|float quoted string, or numeric value unchanged
     * @throws \Exception on unsupported value type
     */
    public static function quote($value)
    {
        return self::cm()->quote($value);
    }

    public static function exec($sql): int|false
    {
        // FIXME: Log::debug('')
        if (self::$global_debug) {
            echo "SQL-EXEC: $sql\n";
        }
        return self::cm()->exec($sql);
    }

    /** @param array $bindings */
    public static function query($sql, $bindings = []): static
    {
        $q = new static;
        $q->query = self::cm()->raw($sql, $bindings);
        return $q;
    }


    public function execute(): static
    {
        $sql = $this->toSql();
        $bindings = $this->getBindings();

        if ($this->isDebug()) {
            echo "SQL: $sql\n";
            if ($bindings) {
                echo "BND: " . print_r($bindings, true);
            }
        }

        $this->query = self::cm()->raw($sql, $bindings);
        return $this;
    }

    public static function lastInsertId(): string|false
    {
        return self::cm()->lastInsertId();
    }

    // What should we do?
    public function affected(): int
    {
        return $this->query->rowCount();
    }

    public function setFetchStyle($style): static
    {
        $this->fetch_style = $style;
        return $this;
    }

    // Fetching
    public function get($style = null)
    {
        if ($style == null) {
            $style = $this->fetch_style;
        }

        if ($this->query === null) {
            $this->prepareSelect($style);
            $this->execute();
        }

        $this->setQueryMode($style);
        $result = $this->query->fetch();
        return $this->transformResult($result, $style);
    }

    public function single()
    {
        //TODO: ResultNotFoundException
        $res = $this->get(PDO::FETCH_NUM);
        return $res[0];
    }

    public function all($style = null, $callback = null)
    {
        if ($style == null) {
            $style = $this->fetch_style;
        }

        $this->prepareSelect($style);
        $this->execute();
        $this->setQueryMode($style);
        $data = $this->query->fetchAll();
        $newdata = [];
        foreach($data as &$row) {
            $row = $this->transformResult($row, $style);
            if ($callback) {
                $row = $callback($row);
            }
            $newdata[] = $row;
        }
        return $newdata;
    }

    public function singleAll()
    {
        return $this->all(
            PDO::FETCH_NUM, function ($row) {
                return $row[0];
            }
        );
    }

    public function first($style = null)
    {
        $old_limit = $this->getLimit();
        $this->limit(1);
        $result = $this->get($style);
        $this->limit($old_limit);

        $this->query->closeCursor();
        $this->query = null;

        return $result;
    }

    private function prepareSelect($style)
    {
        if (is_object($style)) {
            $this->selectForModel(get_class($style));
        } else if (is_class($style)) {
            $this->selectForModel($style);
        }
    }

    private function setQueryMode($style)
    {
        if (is_array($style)) {
            $this->query->setFetchMode(PDO::FETCH_ASSOC);
        } else if (is_object($style)) {
            $this->query->setFetchMode(PDO::FETCH_INTO, $style);
        } else if (is_class($style)) {
            $this->query->setFetchMode(PDO::FETCH_CLASS, $style);
        } else if(is_string($style)) {
            $this->query->setFetchMode(PDO::FETCH_ASSOC);
        } else {
            $this->query->setFetchMode($style);
        }
    }

    private function transformResult($result, $style)
    {
        if($result === null) {
            return null;
        }

        if (is_array($style)) {
            $r = [];
            foreach ($style as $key) {
                $r[$key] = $result[$key];
            }
            return $r;
        } else if (is_object($style)) {
            return $style;
        } else if (is_class($style)) {
            return $result;
        } else if (is_string($style)) {
            return $result[$style];
        } else {
            return $result;
        }
    }

    // Fetch next?
    // @Deprecated
    public function fetch($pdo_fetch_style = PDO::FETCH_OBJ)
    {
        if ($this->query === null) {
            $this->execute(true);
        }

        if (is_class($pdo_fetch_style)) {
            $this->query->setFetchMode(PDO::FETCH_CLASS, $pdo_fetch_style);
        } else {
            $this->query->setFetchMode($pdo_fetch_style);
        }

        return $this->query->fetch();
    }

    // @Deprecated
    public function fetchAll($pdo_fetch_style = PDO::FETCH_OBJ): array
    {
        $this->execute(true);

        if (is_class($pdo_fetch_style)) {
            $this->query->setFetchMode(PDO::FETCH_CLASS, $pdo_fetch_style);
        } else {
            $this->query->setFetchMode($pdo_fetch_style);
        }
        return $this->query->fetchAll();
    }

    /**
     * NOTE, XXX: This is public, becouse mysql need to check if
     * connection is within transaction.
     * Maybe we should make an alias function transactionLevel()
     */
    public static function getTxCounter(): int
    {
        return self::tm()->level();
    }

    // Transactions
    public static function beginTransaction(): void
    {
        self::tm()->begin();
    }

    /** @throws \Exception */
    public static function commit()
    {
        self::tm()->commit();
    }

    /** @throws \Exception */
    public static function rollBack(): void
    {
        self::tm()->rollBack();
    }

    /**
     * @param bool $suppress
     * @return bool|void
     * @throws \Exception
     */
    public static function transaction($cb, $suppress = false)
    {
        return self::tm()->run($cb, $suppress);
    }

    public static function driverName()
    {
        return self::cm()->driverName();
    }
}

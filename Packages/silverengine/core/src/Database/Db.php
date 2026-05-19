<?php
declare(strict_types=1);

namespace Silver\Database;

use \PDO;

abstract class Db
{
    private static $dbs = [];
    private static $default = null;
    private static $global_debug = false;
    private $debug = null;
    private $query = null;
    private static $tx_counter = [];

    private $fetch_style = PDO::FETCH_OBJ;

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
        self::$dbs[$name] = function () use ($name, $dsn, $username, $password) {
            $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

            if (str_starts_with($dsn, 'mysql:') && defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES 'utf8'";
            }

            return new PDO($dsn, $username, $password, $options);
        };
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
        if (!isset(self::$dbs[ $name ])) {
            throw new \Exception("Connection '$name' not found.");
        }
        self::$default = $name;
    }

    public static function withConnection($name, $cb): void
    {
        $prev = self::$default;
        self::setConnection($name);
        try {
            $cb();
        } finally {
            self::$default = $prev;
        }
    }

    public static function connections(): array
    {
        return array_keys(self::$dbs);
    }

    /** @throws \Exception */
    public static function connection($name = null): PDO
    {
        if ($name === null) {
            $name = self::$default;
        }

        //        dd($name);

        if ($name === null) {
            throw new \Exception("Not default connection found.");
        }

        $db = self::$dbs[$name];

        // Lazy loading
        if ($db and is_callable($db)) {
            $db = self::$dbs[ $name ] = $db();
        }

        if (!$db) {
            throw new \Exception("Connection '$name' not found.");
        }

        return $db;
    }

    /**
     * @return string|int|float quoted string, or numeric value unchanged
     * @throws \Exception on unsupported value type
     */
    public static function quote($value)
    {
        switch ($type = gettype($value)) {
        case 'string':
            return self::connection()->quote($value);
        case 'integer':
        case 'double':
            return $value;
        default:
            throw new \Exception("Unable to quote value with type: $type");
        }
    }

    /** @param array $bindings */
    private static function raw($sql, $bindings = []): \PDOStatement
    {
        $db = self::connection();
        $stmt = $db->prepare($sql);
        $stmt->execute($bindings);

        return $stmt;
    }

    public static function exec($sql): int|false
    {
        // FIXME: Log::debug('')
        if (self::$global_debug) {
            echo "SQL-EXEC: $sql\n";
        }
        return self::connection()->exec($sql);
    }

    /** @param array $bindings */
    public static function query($sql, $bindings = []): static
    {
        $q = new static;
        $q->query = self::raw($sql, $bindings);
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

        $this->query = self::raw($sql, $bindings);
        return $this;
    }

    public static function lastInsertId(): string|false
    {
        return self::connection()->lastInsertId();
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
        } else if (is_string($style) && class_exists($style)) {
            $this->selectForModel($style);
        }
    }

    private function setQueryMode($style)
    {
        if (is_array($style)) {
            $this->query->setFetchMode(PDO::FETCH_ASSOC); 
        } else if (is_object($style)) {
            $this->query->setFetchMode(PDO::FETCH_INTO, $style);
        } else if (is_string($style) && class_exists($style)) {
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
        } else if (is_string($style) && class_exists($style)) {
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

        if (is_string($pdo_fetch_style) && class_exists($pdo_fetch_style)) {
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

        if (is_string($pdo_fetch_style) && class_exists($pdo_fetch_style)) {
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
        $db = self::$default;

        if (!isset(self::$tx_counter[ $db ])) {
            self::$tx_counter[ $db ] = 0;
        }

        return self::$tx_counter[ $db ];
    }

    private static function setTxCounter($num): int
    {
        $db = self::$default;

        return self::$tx_counter[ $db ] = $num;
    }

    /** @param int $delta */
    private static function incTxCounter($delta = 1): int
    {
        $num = self::getTxCounter();
        self::setTxCounter($num + $delta);

        return $num + $delta;
    }

    // Transactions
    public static function beginTransaction(): void
    {
        $conn = self::connection();
        $level = self::incTxCounter();

        if ($level == 1) {
            $conn->beginTransaction();
        } else {
            self::exec('SAVEPOINT LEVEL' . $level);
        }
    }

    /** @throws \Exception */
    public static function commit()
    {
        $conn = self::connection();
        $level = self::incTxCounter(-1) + 1;

        if ($level < 1) {
            throw new \Exception("There is no active transaction.");
        } elseif ($level == 1) {
            $conn->commit();
        } else {
            self::exec('RELEASE SAVEPOINT LEVEL' . $level);
        }
    }

    /** @throws \Exception */
    public static function rollBack(): void
    {
        $conn = self::connection();
        $level = self::incTxCounter(-1) + 1;

        if ($level < 1) {
            throw new \Exception("There is no active transaction.");
        } elseif ($level == 1) {
            $conn->rollBack();
        } else {
            self::exec('ROLLBACK TO SAVEPOINT LEVEL' . $level);
        }
    }

    /**
     * @param bool $suppress
     * @return bool|void
     * @throws \Exception
     */
    public static function transaction($cb, $suppress = false)
    {
        try {
            self::beginTransaction();
            $cb();

            return self::commit();
        } catch (\Exception $e) {
            self::rollBack();
            if ($suppress) {
                return false;
            } else {
                throw $e;
            }
        }
    }

    public static function driverName()
    {
        $conn = self::connection();

        return $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
}

<?php
declare(strict_types=1);

namespace Silver\Orm\Contracts;

use PDO;
use PDOStatement;
use Silver\Orm\Connection\Driver;

interface ConnectionInterface
{
    public function pdo(?string $name = null): PDO;

    public function driver(?string $name = null): Driver;

    public function defaultName(): string;

    public function quote(mixed $value, ?string $name = null): string;

    public function exec(string $sql, ?string $name = null): int;

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function raw(string $sql, array $bindings = [], ?string $name = null): PDOStatement;

    public function lastInsertId(?string $name = null): string;
}

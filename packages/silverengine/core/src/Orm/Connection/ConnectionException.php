<?php
declare(strict_types=1);

namespace Silver\Orm\Connection;

use RuntimeException;

final class ConnectionException extends RuntimeException
{
    public static function notFound(string $name): self
    {
        return new self("Connection '{$name}' is not registered.");
    }

    public static function noDefault(): self
    {
        return new self('No default connection has been set.');
    }

    public static function unquotable(string $type): self
    {
        return new self("Cannot quote value of type {$type}.");
    }
}

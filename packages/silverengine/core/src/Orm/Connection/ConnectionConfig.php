<?php
declare(strict_types=1);

namespace Silver\Orm\Connection;

/**
 * Typed configuration for a single connection registration.
 *
 * Carried alongside the lazy PDO closure so the Schema layer can
 * discover per-connection migration directories without re-reading
 * the global config.
 */
final readonly class ConnectionConfig
{
    public function __construct(
        public Driver  $driver,
        public string  $dsn,
        public ?string $username        = null,
        public ?string $password        = null,
        public ?string $migrationsPath  = null,
        public string  $migrationsTable = 'migrations',
        /** @var array<int, mixed> */
        public array   $options         = [],
    ) {}
}

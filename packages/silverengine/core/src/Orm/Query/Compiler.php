<?php
declare(strict_types=1);

namespace Silver\Orm\Query;

use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Connection\Driver;
use Silver\Orm\Contracts\GrammarInterface;
use Silver\Orm\Query\Grammar\MysqlGrammar;
use Silver\Orm\Query\Grammar\PgsqlGrammar;
use Silver\Orm\Query\Grammar\SqliteGrammar;

/**
 * Resolves the right Grammar for a connection. Stateless per call —
 * grammars are lightweight value objects, allocating a fresh one per
 * compile costs nothing and avoids accidental cross-connection state.
 *
 * Mysql / Pgsql grammars land in P5; until then asking for them
 * throws a clear "not implemented yet" rather than silently degrading
 * to sqlite.
 */
final readonly class Compiler
{
    public function __construct(
        private ConnectionManager $connections,
    ) {}

    public function for(?string $connection = null): GrammarInterface
    {
        return self::grammarFor($this->connections->driver($connection));
    }

    public static function grammarFor(Driver $driver): GrammarInterface
    {
        return match ($driver) {
            Driver::Sqlite => new SqliteGrammar(),
            Driver::Mysql  => new MysqlGrammar(),
            Driver::Pgsql  => new PgsqlGrammar(),
        };
    }
}

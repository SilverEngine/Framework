<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Connection;

use PHPUnit\Framework\TestCase;
use Silver\Orm\Connection\Driver;

final class DriverTest extends TestCase
{
    public function testEnumCasesMatchExpectedDsnPrefixes(): void
    {
        self::assertSame('sqlite', Driver::Sqlite->value);
        self::assertSame('mysql',  Driver::Mysql->value);
        self::assertSame('pgsql',  Driver::Pgsql->value);
    }

    public function testMysqlQuotesWithBackticksAndEscapes(): void
    {
        self::assertSame('`users`',     Driver::Mysql->quoteIdentifier('users'));
        self::assertSame('`us``ers`',   Driver::Mysql->quoteIdentifier('us`ers'));
    }

    public function testAnsiDriversQuoteWithDoubleQuotesAndEscape(): void
    {
        self::assertSame('"users"',     Driver::Sqlite->quoteIdentifier('users'));
        self::assertSame('"users"',     Driver::Pgsql->quoteIdentifier('users'));
        self::assertSame('"us""ers"',   Driver::Pgsql->quoteIdentifier('us"ers'));
    }
}

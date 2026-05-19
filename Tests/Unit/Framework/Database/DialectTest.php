<?php

namespace Tests\Unit\Framework\Database;

use PHPUnit\Framework\TestCase;
use Silver\Database\Dialect;
use Silver\Database\Parts\ColumnDef;

/**
 * Locks the extracted dialect Strategy: known drivers map to their
 * Pascal segment, unknown drivers fall back via ucfirst, and classFor
 * returns the dialect variant when it exists else the base class —
 * identical to the old inline Compiler logic.
 */
class DialectTest extends TestCase
{
    public function testSegmentForKnownDrivers(): void
    {
        $this->assertSame('Sqlite', Dialect::segment('sqlite'));
        $this->assertSame('Mysql', Dialect::segment('mysql'));
        $this->assertSame('Pgsql', Dialect::segment('pgsql'));
    }

    public function testSegmentForUnknownDriverFallsBackToUcfirst(): void
    {
        $this->assertSame('Oracle', Dialect::segment('oracle'));
        $this->assertSame('', Dialect::segment(''));
        $this->assertSame('', Dialect::segment(null));
    }

    public function testClassForResolvesExistingDialectVariant(): void
    {
        // Parts\Sqlite\ColumnDef exists in the tree.
        $this->assertSame(
            'Silver\Database\Parts\Sqlite\ColumnDef',
            Dialect::classFor(ColumnDef::class, 'sqlite'),
        );
    }

    public function testClassForFallsBackToBaseWhenNoVariant(): void
    {
        $this->assertSame(ColumnDef::class, Dialect::classFor(ColumnDef::class, 'oracle'));
        $this->assertSame(ColumnDef::class, Dialect::classFor(ColumnDef::class, ''));
    }
}

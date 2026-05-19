<?php

namespace Tests\Unit\Framework\Database;

use PHPUnit\Framework\TestCase;
use Silver\Database\QueryObject;

class QoExplicit extends QueryObject
{
    protected static ?string $table = 'custom_tbl';
    protected static ?string $primaryKey = 'uuid';
}

class QoDerived extends QueryObject
{
}

/**
 * Locks QueryObject table/primary-key resolution after the
 * $_table/$_primary -> $table/$primaryKey rename: explicit static
 * override wins; otherwise table derives from class name, PK = 'id'.
 */
class QueryObjectTest extends TestCase
{
    public function testExplicitStaticOverrideWins(): void
    {
        $this->assertSame('custom_tbl', QoExplicit::tableName());
        $this->assertSame('uuid', QoExplicit::primaryKey());
    }

    public function testTableDerivesFromClassNameAndDefaultPrimaryKey(): void
    {
        $this->assertSame('qo_derived', QoDerived::tableName());
        $this->assertSame('id', QoDerived::primaryKey());
    }
}

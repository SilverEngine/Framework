<?php

namespace Tests\Unit\Framework\Engine;

use PHPUnit\Framework\TestCase;
use Silver\Engine\Command;

/**
 * Locks CLI command resolution after replacing the string match:
 * canonical tokens, the legacy `c` alias for generate, and unknown
 * commands resolving to null (the old default arm).
 */
class CommandTest extends TestCase
{
    public function testCanonicalTokens(): void
    {
        $this->assertSame(Command::Generate, Command::parse('g'));
        $this->assertSame(Command::Delete, Command::parse('d'));
        $this->assertSame(Command::Migrate, Command::parse('migrate'));
        $this->assertSame(Command::Serve, Command::parse('serve'));
        $this->assertSame(Command::Help, Command::parse('help'));
    }

    public function testLegacyCAliasMapsToGenerate(): void
    {
        $this->assertSame(Command::Generate, Command::parse('c'));
    }

    public function testUnknownCommandResolvesToNull(): void
    {
        $this->assertNull(Command::parse('nope'));
        $this->assertNull(Command::parse(''));
    }
}

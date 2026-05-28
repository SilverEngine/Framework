<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Auth;

use PHPUnit\Framework\TestCase;
use Silver\Auth\Hash;

final class HashTest extends TestCase
{
    public function testMakeProducesArgon2idHash(): void
    {
        $hash = Hash::make('correcthorsebatterystaple');
        $this->assertStringStartsWith('$argon2id$', $hash);
    }

    public function testCheckPassesForCorrectPassword(): void
    {
        $hash = Hash::make('secret');
        $this->assertTrue(Hash::check('secret', $hash));
    }

    public function testCheckFailsForWrongPassword(): void
    {
        $hash = Hash::make('secret');
        $this->assertFalse(Hash::check('SECRET', $hash));
        $this->assertFalse(Hash::check('', $hash));
    }

    public function testCheckSafelyReturnsFalseForEmptyHash(): void
    {
        $this->assertFalse(Hash::check('anything', ''));
    }

    public function testNeedsRehashReturnsBoolean(): void
    {
        $hash = Hash::make('secret');
        $this->assertFalse(Hash::needsRehash($hash), 'fresh hash should not need rehash');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Framework\Core;

use PHPUnit\Framework\TestCase;
use Silver\Core\Container;
use stdClass;

/**
 * Cover the global helpers in Core/helpers.php. Today these are env() and
 * is_class(); add coverage here as the helper surface grows.
 */
final class HelpersTest extends TestCase
{
    // -- is_class() ----------------------------------------------------

    public function testIsClassTrueForExistingClassName(): void
    {
        $this->assertTrue(is_class(Container::class));
        $this->assertTrue(is_class(stdClass::class));
    }

    public function testIsClassFalseForUnknownString(): void
    {
        $this->assertFalse(is_class('Nope\\Missing\\Class'));
        $this->assertFalse(is_class(''));
    }

    public function testIsClassFalseForNonStrings(): void
    {
        $this->assertFalse(is_class(null));
        $this->assertFalse(is_class(42));
        $this->assertFalse(is_class(true));
        $this->assertFalse(is_class(['foo']));
        $this->assertFalse(is_class(new stdClass()));
    }

    public function testIsClassFalseForFunctionName(): void
    {
        // Functions aren't classes, even when they exist.
        $this->assertFalse(is_class('strlen'));
    }

    // -- env() ---------------------------------------------------------

    public function testEnvReturnsDefaultWhenKeyMissing(): void
    {
        // Make sure we're using a key that's not in $_ENV / $_SERVER.
        unset($_ENV['DEFINITELY_NOT_SET_XYZ'], $_SERVER['DEFINITELY_NOT_SET_XYZ']);
        $this->assertSame('fallback', env('DEFINITELY_NOT_SET_XYZ', 'fallback'));
        $this->assertNull(env('DEFINITELY_NOT_SET_XYZ'));
    }

    public function testEnvCoercesStringTruthValues(): void
    {
        $_ENV['HTEST_BOOL_T'] = 'true';
        $_ENV['HTEST_BOOL_F'] = 'false';
        $_ENV['HTEST_NULL']   = 'null';
        $_ENV['HTEST_EMPTY']  = 'empty';

        $this->assertTrue(env('HTEST_BOOL_T'));
        $this->assertFalse(env('HTEST_BOOL_F'));
        $this->assertNull(env('HTEST_NULL'));
        $this->assertSame('', env('HTEST_EMPTY'));

        unset($_ENV['HTEST_BOOL_T'], $_ENV['HTEST_BOOL_F'], $_ENV['HTEST_NULL'], $_ENV['HTEST_EMPTY']);
    }

    public function testEnvReturnsRawStringWhenNotASentinel(): void
    {
        $_ENV['HTEST_RAW'] = 'literal value';
        $this->assertSame('literal value', env('HTEST_RAW'));
        unset($_ENV['HTEST_RAW']);
    }
}

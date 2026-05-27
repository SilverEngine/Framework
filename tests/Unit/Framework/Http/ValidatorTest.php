<?php

declare(strict_types=1);

namespace Tests\Unit\Framework\Http;

use PHPUnit\Framework\TestCase;
use Silver\Http\Validator;

/**
 * Locks in the rule surface that actually works (min / max / required /
 * match). The previously-shipped `unique` and `exist` stubs silently
 * passed every value — that lying behavior was deleted; consumers using
 * those rule names will now fail loud (method-not-found), which is the
 * correct outcome for an unimplemented rule.
 */
final class ValidatorTest extends TestCase
{
    // -- required ----------------------------------------------------

    public function testRequiredFailsOnEmptyString(): void
    {
        $errors = Validator::check(['email' => ''], ['email' => 'required']);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('email is required', $errors[0]);
    }

    public function testRequiredFailsOnMissingKey(): void
    {
        $errors = Validator::check([], ['email' => 'required']);
        $this->assertNotEmpty($errors);
    }

    public function testRequiredPassesOnNonEmptyValue(): void
    {
        $errors = Validator::check(['email' => 'a@b.c'], ['email' => 'required']);
        $this->assertSame([], $errors);
        $this->assertTrue(Validator::pass());
    }

    // -- min / max ---------------------------------------------------

    public function testMinFailsBelowThreshold(): void
    {
        $errors = Validator::check(['pw' => 'abc'], ['pw' => 'min:8']);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('at least 8 characters', $errors[0]);
    }

    public function testMinPassesAtAndAboveThreshold(): void
    {
        $this->assertSame([], Validator::check(['pw' => '12345678'], ['pw' => 'min:8']));
        $this->assertSame([], Validator::check(['pw' => '123456789'], ['pw' => 'min:8']));
    }

    public function testMaxFailsAboveThreshold(): void
    {
        $errors = Validator::check(['handle' => 'way_too_long_handle'], ['handle' => 'max:10']);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('less than 10 characters', $errors[0]);
    }

    public function testMaxPassesAtAndBelowThreshold(): void
    {
        $this->assertSame([], Validator::check(['handle' => '1234567890'], ['handle' => 'max:10']));
        $this->assertSame([], Validator::check(['handle' => 'short'], ['handle' => 'max:10']));
    }

    // -- match -------------------------------------------------------

    public function testMatchFailsWhenValuesDiffer(): void
    {
        $errors = Validator::check(
            ['password' => 'abc', 'password_confirm' => 'xyz'],
            ['password_confirm' => 'match:password'],
        );
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('does not match', $errors[0]);
    }

    public function testMatchPassesWhenValuesAreEqual(): void
    {
        $errors = Validator::check(
            ['password' => 'abc', 'password_confirm' => 'abc'],
            ['password_confirm' => 'match:password'],
        );
        $this->assertSame([], $errors);
    }

    // -- chained rules -----------------------------------------------

    public function testChainedRulesAllRun(): void
    {
        // Both rules should fail and produce two errors for the same field.
        $errors = Validator::check(['pw' => ''], ['pw' => 'required|min:8']);
        $this->assertCount(2, $errors);
    }

    public function testGetReturnsErrorsForKey(): void
    {
        Validator::check(['pw' => ''], ['pw' => 'required|min:8']);
        $this->assertCount(2, Validator::get('pw'));
        $this->assertSame([], Validator::get('nonexistent'));
    }

    public function testPassReturnsTrueAfterCleanValidation(): void
    {
        Validator::check(['name' => 'Lex'], ['name' => 'required|min:2']);
        $this->assertTrue(Validator::pass());
    }

    public function testPassReturnsFalseAfterFailedValidation(): void
    {
        Validator::check(['name' => ''], ['name' => 'required']);
        $this->assertFalse(Validator::pass());
    }

    // -- contract: unimplemented rules fail loud ---------------------

    public function testUnimplementedRuleFailsLoudly(): void
    {
        // Used to silently pass — now blows up so dev notices the rule
        // they typed isn't real.
        $this->expectException(\Throwable::class);
        Validator::check(['email' => 'x@y'], ['email' => 'unique']);
    }
}

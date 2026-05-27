<?php

declare(strict_types=1);

namespace Tests\Unit\Framework\Http;

use PHPUnit\Framework\TestCase;
use Silver\Support\Facades\Validator as ValidatorFacade;
use Silver\Http\Validator;

/**
 * Locks in the instance-based Validator surface (no more shared static
 * state). Two `check()` calls in the same request produce two independent
 * results — the bug class that the old `static $errors` made possible
 * cannot exist any more.
 *
 * Both call styles are tested:
 *   - direct instance (`(new Validator)->check(...)`) — what controllers
 *     using DI will see
 *   - facade (`ValidatorFacade::check(...)`) — the static-style entry
 *     point preserved for ergonomics, container-resolved underneath
 */
final class ValidatorTest extends TestCase
{
    private Validator $v;

    protected function setUp(): void
    {
        $this->v = new Validator();
    }

    // -- required ----------------------------------------------------

    public function testRequiredFailsOnEmptyString(): void
    {
        $r = $this->v->check(['email' => ''], ['email' => 'required']);
        $this->assertTrue($r->fails());
        $this->assertStringContainsString('email is required', $r->forField('email')[0]);
    }

    public function testRequiredFailsOnMissingKey(): void
    {
        $this->assertTrue($this->v->check([], ['email' => 'required'])->fails());
    }

    public function testRequiredPassesOnNonEmptyValue(): void
    {
        $this->assertTrue($this->v->check(['email' => 'a@b.c'], ['email' => 'required'])->passes());
    }

    // -- min / max ---------------------------------------------------

    public function testMinFailsBelowThreshold(): void
    {
        $r = $this->v->check(['pw' => 'abc'], ['pw' => 'min:8']);
        $this->assertTrue($r->fails());
        $this->assertStringContainsString('at least 8 characters', $r->forField('pw')[0]);
    }

    public function testMinPassesAtAndAboveThreshold(): void
    {
        $this->assertTrue($this->v->check(['pw' => '12345678'], ['pw' => 'min:8'])->passes());
        $this->assertTrue($this->v->check(['pw' => '123456789'], ['pw' => 'min:8'])->passes());
    }

    public function testMaxFailsAboveThreshold(): void
    {
        $r = $this->v->check(['handle' => 'way_too_long_handle'], ['handle' => 'max:10']);
        $this->assertTrue($r->fails());
        $this->assertStringContainsString('less than 10 characters', $r->forField('handle')[0]);
    }

    public function testMaxPassesAtAndBelowThreshold(): void
    {
        $this->assertTrue($this->v->check(['handle' => '1234567890'], ['handle' => 'max:10'])->passes());
        $this->assertTrue($this->v->check(['handle' => 'short'], ['handle' => 'max:10'])->passes());
    }

    // -- match -------------------------------------------------------

    public function testMatchFailsWhenValuesDiffer(): void
    {
        $r = $this->v->check(
            ['password' => 'abc', 'password_confirm' => 'xyz'],
            ['password_confirm' => 'match:password'],
        );
        $this->assertTrue($r->fails());
        $this->assertStringContainsString('does not match', $r->forField('password_confirm')[0]);
    }

    public function testMatchPassesWhenValuesAreEqual(): void
    {
        $r = $this->v->check(
            ['password' => 'abc', 'password_confirm' => 'abc'],
            ['password_confirm' => 'match:password'],
        );
        $this->assertTrue($r->passes());
    }

    // -- chained rules + result API ----------------------------------

    public function testChainedRulesAllRun(): void
    {
        $r = $this->v->check(['pw' => ''], ['pw' => 'required|min:8']);
        $this->assertCount(2, $r->forField('pw'));
        $this->assertCount(2, $r->all());
    }

    public function testForFieldReturnsEmptyListForUnknownField(): void
    {
        $r = $this->v->check(['pw' => ''], ['pw' => 'required']);
        $this->assertSame([], $r->forField('nonexistent'));
        $this->assertFalse($r->hasField('nonexistent'));
        $this->assertTrue($r->hasField('pw'));
    }

    public function testToArrayExposesFullErrorMap(): void
    {
        $r = $this->v->check(
            ['email' => '', 'pw' => 'a'],
            ['email' => 'required', 'pw' => 'min:8'],
        );
        $map = $r->toArray();
        $this->assertArrayHasKey('email', $map);
        $this->assertArrayHasKey('pw', $map);
    }

    // -- the bug-class fix this refactor is about --------------------

    public function testTwoSequentialChecksDoNotClobberEachOther(): void
    {
        $first  = $this->v->check(['email' => ''], ['email' => 'required']);
        $second = $this->v->check(['email' => 'a@b.c'], ['email' => 'required']);

        // Used to fail in the static implementation — the second check
        // would reset `static $errors` and the first result would lose
        // its state. With ValidationResult holding its own data, the
        // two results live independently.
        $this->assertTrue($first->fails());
        $this->assertTrue($second->passes());
    }

    // -- contract: unimplemented rules fail loud ---------------------

    public function testUnimplementedRuleFailsLoudly(): void
    {
        $this->expectException(\Throwable::class);
        $this->v->check(['email' => 'x@y'], ['email' => 'unique']);
    }

    // -- facade smoke ------------------------------------------------

    public function testFacadeDelegatesToContainerResolvedInstance(): void
    {
        $r = ValidatorFacade::check(['name' => 'Lex'], ['name' => 'required|min:2']);
        $this->assertTrue($r->passes());
    }
}

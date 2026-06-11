<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Http;

use PHPUnit\Framework\TestCase;
use Silver\Http\Validator;

/**
 * F8 — Validation v2: conditional rules + closure rules + array rule
 * syntax (the vehicle for closures).
 */
final class ValidatorV2Test extends TestCase
{
    private Validator $v;

    protected function setUp(): void
    {
        $this->v = new Validator();
    }

    // ---------- required_if ----------

    public function testRequiredIfTriggersWhenOtherFieldMatches(): void
    {
        $result = $this->v->check(
            ['type' => 'company', 'vat_id' => ''],
            ['vat_id' => 'required_if:type,company'],
        );

        self::assertFalse($result->passes());
    }

    public function testRequiredIfPassesWhenOtherFieldDiffers(): void
    {
        $result = $this->v->check(
            ['type' => 'person', 'vat_id' => ''],
            ['vat_id' => 'required_if:type,company'],
        );

        self::assertTrue($result->passes());
    }

    public function testRequiredIfPassesWhenValuePresent(): void
    {
        $result = $this->v->check(
            ['type' => 'company', 'vat_id' => 'SI123'],
            ['vat_id' => 'required_if:type,company'],
        );

        self::assertTrue($result->passes());
    }

    // ---------- required_unless ----------

    public function testRequiredUnlessTriggersWhenOtherFieldDiffers(): void
    {
        $result = $this->v->check(
            ['auth' => 'password', 'token' => ''],
            ['token' => 'required_unless:auth,password'],
        );

        self::assertTrue($result->passes());

        $result = $this->v->check(
            ['auth' => 'token', 'token' => ''],
            ['token' => 'required_unless:auth,password'],
        );

        self::assertFalse($result->passes());
    }

    // ---------- closure rules ----------

    public function testClosureRuleFailsWithItsMessage(): void
    {
        $result = $this->v->check(
            ['slug' => 'Admin'],
            ['slug' => [
                'required',
                fn (mixed $value, array $data): string|false => strtolower((string) $value) === 'admin'
                    ? 'KEY is reserved.'
                    : false,
            ]],
        );

        self::assertFalse($result->passes());
        self::assertSame('slug is reserved.', $result->forField('slug')[0]);
    }

    public function testClosureRulePasses(): void
    {
        $result = $this->v->check(
            ['slug' => 'blog'],
            ['slug' => ['required', fn (mixed $v, array $d): string|false => false]],
        );

        self::assertTrue($result->passes());
    }

    public function testClosureReceivesSiblingData(): void
    {
        $result = $this->v->check(
            ['from' => '10', 'to' => '5'],
            ['to' => [
                fn (mixed $value, array $data): string|false => (int) $value < (int) $data['from']
                    ? 'KEY must be after from.'
                    : false,
            ]],
        );

        self::assertFalse($result->passes());
    }

    // ---------- array rule syntax ----------

    public function testArraySyntaxWithStringRules(): void
    {
        $result = $this->v->check(
            ['email' => 'not-an-email'],
            ['email' => ['required', 'email', 'max:255']],
        );

        self::assertFalse($result->passes());
        self::assertCount(1, $result->forField('email'));
    }

    public function testNullableInArrayFormSkipsClosures(): void
    {
        $called = false;
        $result = $this->v->check(
            [],
            ['nick' => [
                'nullable',
                function (mixed $v, array $d) use (&$called): string|false {
                    $called = true;
                    return 'KEY should not run.';
                },
            ]],
        );

        self::assertTrue($result->passes());
        self::assertFalse($called);
    }

    // ---------- legacy snake-free rules keep working ----------

    public function testExistingPipeStringRulesUnchanged(): void
    {
        $result = $this->v->check(
            ['email' => 'a@b.si', 'age' => '30'],
            ['email' => 'required|email', 'age' => 'required|integer'],
        );

        self::assertTrue($result->passes());
    }
}

<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Http;

use PHPUnit\Framework\TestCase;
use Silver\Http\Validator;

final class ValidatorRulesTest extends TestCase
{
    private Validator $v;

    protected function setUp(): void
    {
        $this->v = new Validator();
    }

    public function testEmailRejectsBadShape(): void
    {
        $this->assertTrue($this->v->check(['email' => 'not-an-email'], ['email' => 'email'])->fails());
        $this->assertTrue($this->v->check(['email' => 'a@b.c'], ['email' => 'email'])->passes());
    }

    public function testUrlRejectsBadShape(): void
    {
        $this->assertTrue($this->v->check(['link' => 'not-a-url'], ['link' => 'url'])->fails());
        $this->assertTrue($this->v->check(['link' => 'https://example.com'], ['link' => 'url'])->passes());
    }

    public function testInRule(): void
    {
        $this->assertTrue($this->v->check(['role' => 'admin'], ['role' => 'in:admin,user'])->passes());
        $this->assertTrue($this->v->check(['role' => 'pirate'], ['role' => 'in:admin,user'])->fails());
    }

    public function testIntegerRule(): void
    {
        $this->assertTrue($this->v->check(['age' => '42'], ['age' => 'integer'])->passes());
        $this->assertTrue($this->v->check(['age' => 'forty'], ['age' => 'integer'])->fails());
    }

    public function testNumericRule(): void
    {
        $this->assertTrue($this->v->check(['amt' => '3.14'], ['amt' => 'numeric'])->passes());
        $this->assertTrue($this->v->check(['amt' => 'pie'], ['amt' => 'numeric'])->fails());
    }

    public function testNullableShortCircuitsRemainingRules(): void
    {
        $r = $this->v->check(['middle_name' => ''], ['middle_name' => 'nullable|min:3']);
        $this->assertTrue($r->passes());

        $r = $this->v->check(['middle_name' => 'Jo'], ['middle_name' => 'nullable|min:3']);
        $this->assertTrue($r->fails());
    }

    public function testConfirmedRequiresPasswordConfirmation(): void
    {
        $r = $this->v->check(
            ['password' => 'secret123', 'password_confirmation' => 'secret123'],
            ['password' => 'confirmed'],
        );
        $this->assertTrue($r->passes());

        $r = $this->v->check(
            ['password' => 'secret123', 'password_confirmation' => 'mismatch'],
            ['password' => 'confirmed'],
        );
        $this->assertTrue($r->fails());
    }

    public function testRegexRule(): void
    {
        $r = $this->v->check(['code' => 'ABC-123'], ['code' => 'regex:/^[A-Z]{3}-\d{3}$/']);
        $this->assertTrue($r->passes());

        $r = $this->v->check(['code' => 'abc-123'], ['code' => 'regex:/^[A-Z]{3}-\d{3}$/']);
        $this->assertTrue($r->fails());
    }

    public function testAlphaAndAlphanumeric(): void
    {
        $this->assertTrue($this->v->check(['n' => 'Lex'], ['n' => 'alpha'])->passes());
        $this->assertTrue($this->v->check(['n' => 'Lex42'], ['n' => 'alpha'])->fails());
        $this->assertTrue($this->v->check(['n' => 'Lex42'], ['n' => 'alphanumeric'])->passes());
        $this->assertTrue($this->v->check(['n' => 'Lex 42'], ['n' => 'alphanumeric'])->fails());
    }

    public function testBetweenRuleAppliesToStringLength(): void
    {
        $this->assertTrue($this->v->check(['handle' => 'Lex'], ['handle' => 'between:2,10'])->passes());
        $this->assertTrue($this->v->check(['handle' => 'x'], ['handle' => 'between:2,10'])->fails());
        $this->assertTrue($this->v->check(['handle' => 'way_too_long'], ['handle' => 'between:2,10'])->fails());
    }

    public function testRequiredHandlesArrayAndTrimmedString(): void
    {
        $this->assertTrue($this->v->check(['tags' => []], ['tags' => 'required'])->fails());
        $this->assertTrue($this->v->check(['name' => '   '], ['name' => 'required'])->fails());
    }
}

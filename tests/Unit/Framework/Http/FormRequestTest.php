<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Http;

use PHPUnit\Framework\TestCase;
use Silver\Core\App;
use Silver\Core\DI;
use Silver\Http\AuthorizationException;
use Silver\Http\FormRequest;
use Silver\Http\ValidationException;

/**
 * FormRequest lifecycle:
 * - DI::call() autowires the request, then calls validateResolved()
 * - prepareForValidation() runs first
 * - authorize() false → AuthorizationException
 * - rules() fail → ValidationException carrying the per-field map
 * - validated() returns only the rule-covered subset
 * - "old input" strips secrets before flash
 */
final class FormRequestTest extends TestCase
{
    private DI $di;

    protected function setUp(): void
    {
        $this->di          = App::instance()->instances()->make(DI::class);
        $_REQUEST          = [];
        $_POST             = [];
        $_GET              = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    public function testValidPayloadPassesAndActionRuns(): void
    {
        $_REQUEST = ['email' => 'a@b.c', 'password' => '12345678', 'password_confirmation' => '12345678'];
        $_POST    = $_REQUEST;

        $captured = null;
        $action = function (FormRequestFixtures\StoreUserRequest $req) use (&$captured) {
            $captured = $req->validated();
            return 'ok';
        };

        $this->assertSame('ok', $this->di->call($action));
        $this->assertSame('a@b.c', $captured['email']);
        $this->assertSame('12345678', $captured['password']);
    }

    public function testInvalidPayloadThrowsValidationExceptionWithErrorMap(): void
    {
        $_REQUEST = ['email' => 'not-an-email', 'password' => 'short'];
        $_POST    = $_REQUEST;

        try {
            $this->di->call(function (FormRequestFixtures\StoreUserRequest $req): void {});
            $this->fail('expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
            $this->assertArrayHasKey('password', $e->errors());
            $this->assertSame(422, $e->getCode());
        }
    }

    public function testOldInputScrubsPasswordFields(): void
    {
        $_REQUEST = ['email' => 'a@b.c', 'password' => 'short', 'password_confirmation' => 'short'];
        $_POST    = $_REQUEST;

        try {
            $this->di->call(function (FormRequestFixtures\StoreUserRequest $req): void {});
            $this->fail('expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->oldInput ?? []);
            $this->assertArrayNotHasKey('password', $e->oldInput ?? []);
            $this->assertArrayNotHasKey('password_confirmation', $e->oldInput ?? []);
        }
    }

    public function testAuthorizeFalseThrowsAuthorizationException(): void
    {
        $_REQUEST = ['email' => 'a@b.c', 'password' => '12345678', 'password_confirmation' => '12345678'];
        $_POST    = $_REQUEST;

        $this->expectException(AuthorizationException::class);
        $this->di->call(function (FormRequestFixtures\ForbiddenRequest $req): void {});
    }

    public function testPrepareForValidationCanMutateInput(): void
    {
        $_REQUEST = ['name' => '  Lex  '];
        $_POST    = $_REQUEST;

        $captured = null;
        $this->di->call(function (FormRequestFixtures\TrimmingRequest $req) use (&$captured): void {
            $captured = $req->validated();
        });

        $this->assertSame('Lex', $captured['name']);
    }

    public function testValidatedSubsetIsRuleScopedNotEverything(): void
    {
        $_REQUEST = ['email' => 'a@b.c', 'password' => '12345678', 'password_confirmation' => '12345678', 'sneaky_field' => 'attack'];
        $_POST    = $_REQUEST;

        $captured = null;
        $this->di->call(function (FormRequestFixtures\StoreUserRequest $req) use (&$captured): void {
            $captured = $req->validated();
        });

        $this->assertArrayNotHasKey('sneaky_field', $captured);
    }

    public function testCustomMessageOverridesDefault(): void
    {
        $_REQUEST = ['email' => ''];
        $_POST    = $_REQUEST;

        try {
            $this->di->call(function (FormRequestFixtures\CustomMessageRequest $req): void {});
            $this->fail('expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertContains('Email is mandatory', $e->errors()['email']);
        }
    }
}

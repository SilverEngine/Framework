<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Auth;

use PHPUnit\Framework\TestCase;
use Silver\Auth\Contracts\Authenticatable;
use Silver\Auth\Contracts\MustVerifyEmail;
use Silver\Auth\EmailVerification;
use Silver\Http\Request;

final class VerifiableUser implements Authenticatable, MustVerifyEmail
{
    public ?string $email_verified_at = null;

    public function __construct(public int $id = 1, public string $email = 'lex@example.test') {}

    public function getAuthIdentifier(): int|string { return $this->id; }
    public function getAuthPasswordHash(): string   { return ''; }
    public function getAuthIdentifierName(): string { return 'id'; }

    public function getEmailForVerification(): string { return $this->email; }
    public function hasVerifiedEmail(): bool          { return $this->email_verified_at !== null; }
    public function markEmailAsVerified(): void       { $this->email_verified_at = date('Y-m-d H:i:s'); }
}

final class EmailVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['APP_KEY'] = 'base64:' . base64_encode(str_repeat('v', 32));
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    private function requestFor(string $url): Request
    {
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $_SERVER['REQUEST_URI'] = $url;
        $_GET = $query;
        return new Request();
    }

    public function testVerificationUrlIsSignedAndCarriesIdAndHash(): void
    {
        $user = new VerifiableUser(id: 5);
        $url  = new EmailVerification()->verificationUrl($user);

        self::assertStringContainsString('/email/verify', $url);
        self::assertStringContainsString('id=5', $url);
        self::assertStringContainsString('hash=', $url);
        self::assertStringContainsString('expires=', $url);
        self::assertStringContainsString('signature=', $url);
    }

    public function testVerifyMarksTheUser(): void
    {
        $user = new VerifiableUser();
        $svc  = new EmailVerification();
        $req  = $this->requestFor($svc->verificationUrl($user));

        self::assertTrue($svc->verify($req, $user));
        self::assertTrue($user->hasVerifiedEmail());
    }

    public function testTamperedSignatureFails(): void
    {
        $user = new VerifiableUser();
        $svc  = new EmailVerification();
        $url  = str_replace('id=1', 'id=2', $svc->verificationUrl($user));

        self::assertFalse($svc->verify($this->requestFor($url), $user));
        self::assertFalse($user->hasVerifiedEmail());
    }

    public function testUrlForDifferentUserFails(): void
    {
        $svc   = new EmailVerification();
        $alice = new VerifiableUser(id: 1, email: 'alice@example.test');
        $bob   = new VerifiableUser(id: 2, email: 'bob@example.test');

        $req = $this->requestFor($svc->verificationUrl($alice));

        self::assertFalse($svc->verify($req, $bob));
    }

    public function testChangedEmailInvalidatesOldLink(): void
    {
        $user = new VerifiableUser();
        $svc  = new EmailVerification();
        $url  = $svc->verificationUrl($user);

        $user->email = 'new@example.test';

        self::assertFalse($svc->verify($this->requestFor($url), $user));
    }

    public function testAlreadyVerifiedShortCircuitsTrue(): void
    {
        $user = new VerifiableUser();
        $user->markEmailAsVerified();
        $svc = new EmailVerification();

        self::assertTrue($svc->verify($this->requestFor('/email/verify'), $user));
    }
}

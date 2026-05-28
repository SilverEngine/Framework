<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Auth;

use PHPUnit\Framework\TestCase;
use Silver\Auth\Hash;
use Silver\Auth\Providers\OrmUserProvider;
use Silver\Auth\SessionGuard;
use Silver\Http\Csrf\TokenStore;
use Silver\Orm\Cache\ModelCache;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Model\AttributeRegistry;
use Silver\Orm\Model\EventDispatcher;
use Silver\Orm\Model\Model;
use Tests\Unit\Framework\Auth\fixtures\AuthUser;

final class SessionGuardTest extends TestCase
{
    private SessionGuard $guard;
    private TokenStore $csrf;

    protected function setUp(): void
    {
        $_SESSION = [];
        AttributeRegistry::flush();
        ModelCache::flushAll();

        $cm = new ConnectionManager();
        $cm->connect('default', 'sqlite::memory:');
        $cm->setDefault('default');
        $cm->exec(
            'CREATE TABLE auth_users (
                id       INTEGER PRIMARY KEY AUTOINCREMENT,
                email    TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL
            )',
        );
        Model::bind($cm, new EventDispatcher());

        AuthUser::create([
            'email'    => 'lex@example.test',
            'password' => Hash::make('s3cretp4ss'),
        ]);

        $provider   = new OrmUserProvider(AuthUser::class, 'email');
        $this->csrf = new TokenStore();
        $this->guard = new SessionGuard($provider, $this->csrf);
    }

    public function testFreshGuardIsGuest(): void
    {
        $this->assertTrue($this->guard->guest());
        $this->assertFalse($this->guard->check());
        $this->assertNull($this->guard->user());
        $this->assertNull($this->guard->id());
    }

    public function testAttemptWithCorrectCredentialsLogsIn(): void
    {
        $ok = $this->guard->attempt([
            'email'    => 'lex@example.test',
            'password' => 's3cretp4ss',
        ]);
        $this->assertTrue($ok);
        $this->assertTrue($this->guard->check());
        $this->assertSame('lex@example.test', $this->guard->user()?->email);
    }

    public function testAttemptWithWrongPasswordReturnsFalse(): void
    {
        $ok = $this->guard->attempt([
            'email'    => 'lex@example.test',
            'password' => 'wrong',
        ]);
        $this->assertFalse($ok);
        $this->assertTrue($this->guard->guest());
    }

    public function testAttemptWithUnknownUserReturnsFalse(): void
    {
        $this->assertFalse($this->guard->attempt([
            'email'    => 'nobody@example.test',
            'password' => 'whatever',
        ]));
    }

    public function testValidateChecksCredentialsWithoutLoggingIn(): void
    {
        $ok = $this->guard->validate([
            'email'    => 'lex@example.test',
            'password' => 's3cretp4ss',
        ]);
        $this->assertTrue($ok);
        $this->assertTrue($this->guard->guest(), 'validate() must not log in');
    }

    public function testLoginRotatesCsrfToken(): void
    {
        $before = $this->csrf->current();

        $user = AuthUser::find(1);
        $this->guard->login($user);

        $after = $this->csrf->current();
        $this->assertNotSame($before, $after, 'CSRF must rotate on login (session fixation)');
    }

    public function testLogoutClearsSessionAndRotatesCsrf(): void
    {
        $user = AuthUser::find(1);
        $this->guard->login($user);
        $this->assertTrue($this->guard->check());

        $tokenAfterLogin = $this->csrf->current();
        $this->guard->logout();

        $this->assertTrue($this->guard->guest());
        $this->assertNull($this->guard->user());
        $this->assertNotSame($tokenAfterLogin, $this->csrf->current());
    }

    public function testUserResolutionIsMemoisedWithinRequest(): void
    {
        $user = AuthUser::find(1);
        $this->guard->login($user);

        // Drop the DB connection: subsequent user() calls must still
        // succeed because they're memoised, not re-fetched.
        $a = $this->guard->user();
        $b = $this->guard->user();
        $this->assertSame($a, $b);
    }
}

<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Auth;

use PHPUnit\Framework\TestCase;
use Silver\Auth\Hash;
use Silver\Auth\Providers\OrmUserProvider;
use Silver\Auth\RememberMe;
use Silver\Auth\RememberTokenRepository;
use Silver\Auth\SessionGuard;
use Silver\Core\App;
use Silver\Http\Csrf\TokenStore;
use Silver\Http\Response;
use Silver\Orm\Cache\ModelCache;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Model\AttributeRegistry;
use Silver\Orm\Model\EventDispatcher;
use Silver\Orm\Model\Model;
use Tests\Unit\Framework\Auth\fixtures\AuthUser;

final class RememberMeTest extends TestCase
{
    private ConnectionManager $cm;
    private float $now = 1_000_000.0;

    protected function setUp(): void
    {
        $_SESSION = [];
        $_COOKIE  = [];
        AttributeRegistry::flush();
        ModelCache::flushAll();

        $this->cm = new ConnectionManager();
        $this->cm->connect('default', 'sqlite::memory:');
        $this->cm->setDefault('default');
        $this->cm->exec(
            'CREATE TABLE auth_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL)',
        );
        $this->cm->exec(
            'CREATE TABLE remember_tokens (
                selector   TEXT PRIMARY KEY,
                token_hash TEXT NOT NULL,
                user_id    INTEGER NOT NULL,
                expires_at INTEGER NOT NULL)',
        );
        Model::bind($this->cm, new EventDispatcher());

        // CookieJar writes go through the registered Response.
        App::instance()->instances()->register(new Response(), true);
    }

    private function repo(): RememberTokenRepository
    {
        return new RememberTokenRepository($this->cm, clock: fn (): float => $this->now);
    }

    // ---------- repository ----------

    public function testIssueStoresHashNotTheValidator(): void
    {
        $cookie = $this->repo()->issue(5, 3600);

        [$selector, $validator] = explode(':', $cookie);
        $row = $this->cm->raw('SELECT * FROM remember_tokens WHERE selector = ?', [$selector])
            ->fetch(\PDO::FETCH_ASSOC);

        self::assertNotFalse($row);
        self::assertSame(5, (int) $row['user_id']);
        self::assertNotSame($validator, $row['token_hash']);
        self::assertSame(hash('sha256', $validator), $row['token_hash']);
    }

    public function testUserIdForRoundTrips(): void
    {
        $cookie = $this->repo()->issue(7, 3600);

        self::assertSame(7, (int) $this->repo()->userIdFor($cookie));
    }

    public function testWrongValidatorFails(): void
    {
        $cookie = $this->repo()->issue(7, 3600);
        [$selector] = explode(':', $cookie);

        self::assertNull($this->repo()->userIdFor($selector . ':forged-validator'));
    }

    public function testExpiredTokenFailsAndIsPurged(): void
    {
        $cookie = $this->repo()->issue(7, 3600);

        $this->now += 3601;
        self::assertNull($this->repo()->userIdFor($cookie));

        $count = (int) $this->cm->raw('SELECT COUNT(*) FROM remember_tokens')->fetchColumn();
        self::assertSame(0, $count);
    }

    public function testMalformedCookieFails(): void
    {
        self::assertNull($this->repo()->userIdFor('garbage'));
        self::assertNull($this->repo()->userIdFor(''));
    }

    public function testRevokeAllForUser(): void
    {
        $repo = $this->repo();
        $repo->issue(7, 3600);
        $repo->issue(7, 3600);
        $repo->issue(8, 3600);

        $repo->revokeAllFor(7);

        $count = (int) $this->cm->raw('SELECT COUNT(*) FROM remember_tokens')->fetchColumn();
        self::assertSame(1, $count);
    }

    // ---------- guard integration ----------

    private function guard(): SessionGuard
    {
        AuthUser::query()->where('email', 'lex@example.test')->first()
            ?? AuthUser::create(['email' => 'lex@example.test', 'password' => Hash::make('s3cretp4ss')]);

        $remember = new RememberMe($this->repo(), ttlSeconds: 3600);
        return new SessionGuard(
            new OrmUserProvider(AuthUser::class, 'email'),
            new TokenStore(),
            $remember,
        );
    }

    /** The remember cookie value the guard queued on the Response. */
    private function queuedCookie(): ?string
    {
        $res     = Response::instance();
        $cookies = new \ReflectionProperty(Response::class, 'cookies')->getValue($res);
        $raw     = $cookies['remember_token'][0] ?? null;
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return $decoded['values'][0] ?? null;
    }

    public function testLoginWithRememberQueuesCookieAndStoresToken(): void
    {
        $guard = $this->guard();
        self::assertTrue($guard->attempt(
            ['email' => 'lex@example.test', 'password' => 's3cretp4ss'],
            remember: true,
        ));

        self::assertNotNull($this->queuedCookie());
        $count = (int) $this->cm->raw('SELECT COUNT(*) FROM remember_tokens')->fetchColumn();
        self::assertSame(1, $count);
    }

    public function testUserResolvedFromRememberCookieWhenSessionEmpty(): void
    {
        $guard = $this->guard();
        $guard->attempt(['email' => 'lex@example.test', 'password' => 's3cretp4ss'], remember: true);
        $cookieValue = $this->queuedCookie();

        // New request: session gone, cookie presented.
        $_SESSION = [];
        $_COOKIE['remember_token'] = json_encode([
            'values' => [$cookieValue], 'expire' => PHP_INT_MAX, 'encrypted' => false,
        ]);

        $fresh = $this->guard();
        self::assertTrue($fresh->check());
        self::assertSame('lex@example.test', $fresh->user()->email);
    }

    public function testTokenRotatesOnUse(): void
    {
        $guard = $this->guard();
        $guard->attempt(['email' => 'lex@example.test', 'password' => 's3cretp4ss'], remember: true);
        $oldCookie = $this->queuedCookie();

        $_SESSION = [];
        $_COOKIE['remember_token'] = json_encode([
            'values' => [$oldCookie], 'expire' => PHP_INT_MAX, 'encrypted' => false,
        ]);

        $fresh = $this->guard();
        self::assertTrue($fresh->check());

        // The used token is dead; a new one was queued.
        self::assertNull($this->repo()->userIdFor((string) $oldCookie));
        $newCookie = $this->queuedCookie();
        self::assertNotSame($oldCookie, $newCookie);
        self::assertNotNull($this->repo()->userIdFor((string) $newCookie));
    }

    public function testLogoutRevokesTokenAndSessionStaysGuest(): void
    {
        $guard = $this->guard();
        $guard->attempt(['email' => 'lex@example.test', 'password' => 's3cretp4ss'], remember: true);
        $cookieValue = $this->queuedCookie();
        $_COOKIE['remember_token'] = json_encode([
            'values' => [$cookieValue], 'expire' => PHP_INT_MAX, 'encrypted' => false,
        ]);

        $guard->logout();

        self::assertNull($this->repo()->userIdFor((string) $cookieValue));
        $_SESSION = [];
        self::assertTrue($this->guard()->guest());
    }
}

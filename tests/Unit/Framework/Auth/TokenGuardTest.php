<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Auth;

use PHPUnit\Framework\TestCase;
use Silver\Auth\ApiTokenRepository;
use Silver\Auth\Hash;
use Silver\Auth\Providers\OrmUserProvider;
use Silver\Auth\TokenGuard;
use Silver\Orm\Cache\ModelCache;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Model\AttributeRegistry;
use Silver\Orm\Model\EventDispatcher;
use Silver\Orm\Model\Model;
use Tests\Unit\Framework\Auth\fixtures\AuthUser;

final class TokenGuardTest extends TestCase
{
    private ConnectionManager $cm;
    private float $now = 1_000_000.0;

    protected function setUp(): void
    {
        AttributeRegistry::flush();
        ModelCache::flushAll();
        unset($_SERVER['HTTP_AUTHORIZATION']);

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
            'CREATE TABLE api_tokens (
                selector   TEXT PRIMARY KEY,
                token_hash TEXT NOT NULL,
                user_id    INTEGER NOT NULL,
                name       TEXT NOT NULL,
                abilities  TEXT NOT NULL,
                expires_at INTEGER)',
        );
        Model::bind($this->cm, new EventDispatcher());

        AuthUser::create(['email' => 'api@example.test', 'password' => Hash::make('pw')]);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    private function repo(): ApiTokenRepository
    {
        return new ApiTokenRepository($this->cm, clock: fn (): float => $this->now);
    }

    private function guard(): TokenGuard
    {
        return new TokenGuard(new OrmUserProvider(AuthUser::class, 'email'), $this->repo());
    }

    // ---------- repository ----------

    public function testIssueStoresHashAndFindRoundTrips(): void
    {
        $token = $this->repo()->issue(1, 'ci-deploy', ['deploy']);

        $row = $this->cm->raw('SELECT * FROM api_tokens')->fetch(\PDO::FETCH_ASSOC);
        self::assertStringNotContainsString(explode(':', $token)[1], (string) $row['token_hash']);

        $found = $this->repo()->find($token);
        self::assertNotNull($found);
        self::assertSame(1, (int) $found['user_id']);
        self::assertSame(['deploy'], $found['abilities']);
    }

    public function testWrongValidatorFails(): void
    {
        $token = $this->repo()->issue(1, 't', ['*']);
        [$selector] = explode(':', $token);

        self::assertNull($this->repo()->find($selector . ':wrong'));
    }

    public function testExpiredTokenFailsAndPurges(): void
    {
        $token = $this->repo()->issue(1, 't', ['*'], ttlSeconds: 60);

        $this->now += 61;
        self::assertNull($this->repo()->find($token));
        self::assertSame(0, (int) $this->cm->raw('SELECT COUNT(*) FROM api_tokens')->fetchColumn());
    }

    public function testTokenWithoutTtlNeverExpires(): void
    {
        $token = $this->repo()->issue(1, 't', ['*']);

        $this->now += 10_000_000;
        self::assertNotNull($this->repo()->find($token));
    }

    public function testRevokeAllForUser(): void
    {
        $repo = $this->repo();
        $t1 = $repo->issue(1, 'a', ['*']);
        $repo->issue(1, 'b', ['*']);

        $repo->revokeAllFor(1);
        self::assertNull($repo->find($t1));
    }

    // ---------- guard ----------

    public function testGuardResolvesUserFromBearerHeader(): void
    {
        $token = $this->repo()->issue(1, 'cli', ['*']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $guard = $this->guard();
        self::assertTrue($guard->check());
        self::assertSame('api@example.test', $guard->user()->email);
        self::assertSame(1, (int) $guard->id());
    }

    public function testMissingOrMalformedHeaderIsGuest(): void
    {
        self::assertTrue($this->guard()->guest());

        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwdw==';
        self::assertTrue($this->guard()->guest());

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer not-a-real-token';
        self::assertTrue($this->guard()->guest());
    }

    public function testTokenCanChecksAbilities(): void
    {
        $token = $this->repo()->issue(1, 'deploy-bot', ['deploy', 'read']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $guard = $this->guard();
        $guard->user();

        self::assertTrue($guard->tokenCan('deploy'));
        self::assertTrue($guard->tokenCan('read'));
        self::assertFalse($guard->tokenCan('admin'));
    }

    public function testWildcardAbilityAllowsEverything(): void
    {
        $token = $this->repo()->issue(1, 'root', ['*']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $guard = $this->guard();
        $guard->user();

        self::assertTrue($guard->tokenCan('anything-at-all'));
    }

    public function testValidateChecksCredentialsWithoutSession(): void
    {
        $guard = $this->guard();

        self::assertTrue($guard->validate(['email' => 'api@example.test', 'password' => 'pw']));
        self::assertFalse($guard->validate(['email' => 'api@example.test', 'password' => 'no']));
    }
}

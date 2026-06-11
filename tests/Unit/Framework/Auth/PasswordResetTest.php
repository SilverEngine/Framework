<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Auth;

use PHPUnit\Framework\TestCase;
use Silver\Auth\Hash;
use Silver\Auth\PasswordBroker;
use Silver\Auth\PasswordResetRepository;
use Silver\Auth\Providers\OrmUserProvider;
use Silver\Mail\Drivers\ArrayDriver;
use Silver\Mail\Mailer;
use Silver\Mail\Message;
use Silver\Orm\Cache\ModelCache;
use Silver\Orm\Connection\ConnectionManager;
use Silver\Orm\Model\AttributeRegistry;
use Silver\Orm\Model\EventDispatcher;
use Silver\Orm\Model\Model;
use Tests\Unit\Framework\Auth\fixtures\AuthUser;

final class PasswordResetTest extends TestCase
{
    private ConnectionManager $cm;
    private ArrayDriver $mailDriver;
    private float $now = 1_000_000.0;

    protected function setUp(): void
    {
        AttributeRegistry::flush();
        ModelCache::flushAll();
        $_ENV['APP_KEY'] = 'base64:' . base64_encode(random_bytes(32));

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
            'CREATE TABLE password_resets (
                selector   TEXT PRIMARY KEY,
                token_hash TEXT NOT NULL,
                email      TEXT NOT NULL,
                expires_at INTEGER NOT NULL)',
        );
        Model::bind($this->cm, new EventDispatcher());

        AuthUser::create(['email' => 'lex@example.test', 'password' => Hash::make('old-pw')]);

        $this->mailDriver = new ArrayDriver();
    }

    private function repo(): PasswordResetRepository
    {
        return new PasswordResetRepository($this->cm, clock: fn (): float => $this->now);
    }

    private function broker(): PasswordBroker
    {
        return new PasswordBroker(
            new OrmUserProvider(AuthUser::class, 'email'),
            $this->repo(),
            new Mailer($this->mailDriver, fromAddress: 'noreply@app.test'),
        );
    }

    // ---------- repository ----------

    public function testIssueStoresHashOnlyAndEmailForRoundTrips(): void
    {
        $token = $this->repo()->issue('lex@example.test', 3600);

        $row = $this->cm->raw('SELECT * FROM password_resets')->fetch(\PDO::FETCH_ASSOC);
        self::assertStringNotContainsString(explode(':', $token)[1], (string) $row['token_hash']);

        self::assertSame('lex@example.test', $this->repo()->emailFor($token));
    }

    public function testWrongValidatorOrGarbageFails(): void
    {
        $token = $this->repo()->issue('lex@example.test', 3600);
        [$selector] = explode(':', $token);

        self::assertNull($this->repo()->emailFor($selector . ':wrong'));
        self::assertNull($this->repo()->emailFor('no-colon-here'));
        self::assertNull($this->repo()->emailFor(''));
    }

    public function testExpiredTokenFailsAndPurges(): void
    {
        $token = $this->repo()->issue('lex@example.test', 60);

        $this->now += 61;
        self::assertNull($this->repo()->emailFor($token));
        self::assertSame(0, (int) $this->cm->raw('SELECT COUNT(*) FROM password_resets')->fetchColumn());
    }

    public function testReissueInvalidatesPreviousTokenForSameEmail(): void
    {
        $repo  = $this->repo();
        $first = $repo->issue('lex@example.test', 3600);
        $repo->issue('lex@example.test', 3600);

        self::assertNull($repo->emailFor($first));
        self::assertSame(1, (int) $this->cm->raw('SELECT COUNT(*) FROM password_resets')->fetchColumn());
    }

    public function testRevoke(): void
    {
        $repo  = $this->repo();
        $token = $repo->issue('lex@example.test', 3600);

        $repo->revoke($token);
        self::assertNull($repo->emailFor($token));
    }

    // ---------- broker: send ----------

    public function testSendResetLinkMailsTokenUrlToKnownUser(): void
    {
        self::assertTrue($this->broker()->sendResetLink('lex@example.test'));

        self::assertTrue($this->mailDriver->hasSentTo('lex@example.test'));
        $body = $this->mailDriver->sent()[0]->mime()['body'];
        self::assertStringContainsString('/password/reset', $body);
        self::assertStringContainsString('token=', $body);
    }

    public function testSendResetLinkForUnknownEmailSendsNothing(): void
    {
        self::assertFalse($this->broker()->sendResetLink('nobody@example.test'));
        self::assertCount(0, $this->mailDriver->sent());
    }

    public function testCustomMailBuilderIsUsed(): void
    {
        $broker = $this->broker();
        $broker->buildMailUsing(
            fn (string $url, string $email): Message => new Message()
                ->to($email)
                ->subject('Custom reset')
                ->text('Click: ' . $url),
        );

        $broker->sendResetLink('lex@example.test');

        self::assertSame('Custom reset', $this->mailDriver->sent()[0]->subject());
    }

    // ---------- broker: reset ----------

    public function testResetWithValidTokenUpdatesPasswordAndRevokesToken(): void
    {
        $broker = $this->broker();
        $token  = $this->repo()->issue('lex@example.test', 3600);

        self::assertTrue($broker->reset('lex@example.test', $token, 'new-pw'));

        $user = AuthUser::query()->where('email', '=', 'lex@example.test')->first();
        self::assertTrue(Hash::check('new-pw', $user->password));

        // Single-use: the same token cannot reset again.
        self::assertFalse($broker->reset('lex@example.test', $token, 'sneaky'));
    }

    public function testResetWithTokenForDifferentEmailFails(): void
    {
        AuthUser::create(['email' => 'mallory@example.test', 'password' => Hash::make('x')]);
        $token = $this->repo()->issue('mallory@example.test', 3600);

        self::assertFalse($this->broker()->reset('lex@example.test', $token, 'new-pw'));

        $user = AuthUser::query()->where('email', '=', 'lex@example.test')->first();
        self::assertTrue(Hash::check('old-pw', $user->password));
    }

    public function testResetWithGarbageTokenFails(): void
    {
        self::assertFalse($this->broker()->reset('lex@example.test', 'junk:junk', 'new-pw'));
    }
}

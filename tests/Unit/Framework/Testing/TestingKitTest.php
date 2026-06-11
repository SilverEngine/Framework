<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Testing;

use Silver\Core\Route;
use Silver\Http\ArraySessionStore;
use Silver\Http\Contracts\SessionStore;
use Silver\Http\Request;
use Silver\Http\Response;
use Silver\Http\Session;
use Silver\Orm\Query\Builder;
use Silver\Orm\Query\Compiler;
use Silver\Support\Facades\Log;
use Silver\Testing\TestCase;

/**
 * The G2 testing kit, tested with itself: every test here is written
 * the way an app developer would write one against their own routes.
 */
final class TestingKitTest extends TestCase
{
    public function testGetDispatchesARouteAndAssertsOnTheBody(): void
    {
        $this->routes(function (Route $r): void {
            $r->get('/hello', fn (): string => 'hello world');
        });

        $this->get('/hello')
            ->assertOk()
            ->assertSee('hello world')
            ->assertDontSee('goodbye');
    }

    public function testRouteParamsAreCoercedAndInjected(): void
    {
        $this->routes(function (Route $r): void {
            $r->get('/users/{id}', fn (int $id): string => 'user:' . $id);
        });

        $this->get('/users/5')->assertOk()->assertSee('user:5');
    }

    public function testPostDataIsVisibleToRequestInput(): void
    {
        $this->routes(function (Route $r): void {
            $r->post('/submit', fn (Request $req): string => 'name=' . $req->input('name'));
        });

        $this->post('/submit', ['name' => 'lex'])->assertSee('name=lex');
    }

    public function testJsonRequestBodyIsReadableViaRequestJson(): void
    {
        $this->routes(function (Route $r): void {
            $r->post('/api/echo', fn (Request $req): array => ['got' => $req->json('key')]);
        });

        $this->json('POST', '/api/echo', ['key' => 'value'])
            ->assertOk()
            ->assertJson(['got' => 'value'])
            ->assertJsonPath('got', 'value');
    }

    public function testAssertJsonMatchesSubsetNotWholeDocument(): void
    {
        $this->routes(function (Route $r): void {
            $r->get('/api/user', fn (): array => [
                'id'    => 7,
                'name'  => 'Lex',
                'roles' => ['admin', 'owner'],
            ]);
        });

        $this->json('GET', '/api/user')
            ->assertJson(['name' => 'Lex'])
            ->assertJsonPath('roles.1', 'owner');
    }

    public function testUnknownRouteIs404ThroughTheErrorHandler(): void
    {
        $this->get('/definitely-not-registered')->assertNotFound();
    }

    public function testRedirectAssertion(): void
    {
        $this->routes(function (Route $r): void {
            $r->get('/old', function (Response $res): string {
                $res->setCode(302);
                $res->setHeader('Location', '/new');
                return '';
            });
        });

        $this->get('/old')->assertRedirect('/new');
    }

    public function testHeaderAssertion(): void
    {
        $this->routes(function (Route $r): void {
            $r->get('/csv', function (Response $res): string {
                $res->setHeader('Content-Type', 'text/csv');
                return "a,b\n";
            });
        });

        $this->get('/csv')->assertHeader('Content-Type', 'text/csv');
    }

    public function testSessionIsAnArrayFakePerTest(): void
    {
        self::assertInstanceOf(ArraySessionStore::class, app(SessionStore::class));

        $this->routes(function (Route $r): void {
            $r->get('/remember', function (): string {
                Session::set('seen', 'yes');
                return 'ok';
            });
        });

        $this->get('/remember')->assertOk();
        self::assertSame('yes', Session::get('seen'));
    }

    public function testFakeMailCollectsInsteadOfSending(): void
    {
        $mail = $this->fakeMail();

        $this->routes(function (Route $r): void {
            $r->post('/invite', function (Request $req): string {
                mailer()->send(
                    new \Silver\Mail\Message()
                        ->to((string) $req->input('email'))
                        ->subject('Invite')
                        ->text('Join us'),
                );
                return 'sent';
            });
        });

        $this->post('/invite', ['email' => 'friend@example.test'])->assertOk();

        self::assertTrue($mail->hasSentTo('friend@example.test'));
        $this->assertMailSentTo('friend@example.test');
        self::assertCount(1, $mail->sent());
    }

    public function testFakeLogRecordsInsteadOfWritingFiles(): void
    {
        $log = $this->fakeLog();

        $this->routes(function (Route $r): void {
            $r->get('/boom', function (): string {
                Log::error('something broke');
                return 'ok';
            });
        });

        $this->get('/boom')->assertOk();

        $log->assertLogged('error', 'something broke');
        self::assertSame(['something broke'], $log->logged('error'));
    }

    public function testFakeLogRejectsUnknownLevelsLikeTheRealOne(): void
    {
        $log = $this->fakeLog();

        $this->expectException(\Exception::class);
        $log->notALevel('x');
    }

    public function testUseDatabaseGivesInMemorySqliteBoundToModels(): void
    {
        $cm = $this->useDatabase();
        $cm->exec('CREATE TABLE notes (id INTEGER PRIMARY KEY AUTOINCREMENT, body TEXT)');

        $q = new Builder($cm, new Compiler($cm))->from('notes');
        $q->insert(['body' => 'first']);

        self::assertSame(1, new Builder($cm, new Compiler($cm))->from('notes')->count());
        // The container singleton was swapped too, so framework services
        // (auth repositories, etc.) hit the same in-memory database.
        self::assertSame($cm, app(\Silver\Orm\Connection\ConnectionManager::class));
    }

    public function testMigrateRunsMigrationsFromAPath(): void
    {
        $cm = $this->migrate(__DIR__ . '/../Orm/Schema/fixtures/default');

        // The fixture migrations create users + posts; both queryable.
        self::assertSame(0, new Builder($cm, new Compiler($cm))->from('users')->count());
        self::assertSame(0, new Builder($cm, new Compiler($cm))->from('posts')->count());
    }

    public function testEachTestGetsAFreshAppAndRouter(): void
    {
        // Routes registered by earlier tests must not leak into this one.
        self::assertNull(app(Route::class)->find('/hello', 'GET'));
    }
}

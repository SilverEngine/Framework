<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Http;

use Silver\Core\Route;
use Silver\Http\Session;
use Silver\Support\Translator;
use Silver\Testing\TestCase;

/**
 * SetLocale middleware, driven through the G2 testing kit: ?lang=
 * switches (and persists to the session), the session value sticks
 * on later requests, unsupported locales are ignored.
 */
final class SetLocaleMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->config('lang', [
            'path'      => dirname(__DIR__) . '/Support/fixtures/lang',
            'locale'    => 'en',
            'fallback'  => 'en',
            'supported' => ['en', 'sl'],
        ]);

        $this->routes(function (Route $r): void {
            $r->get('/greet', fn (): string => trans('messages.welcome', ['name' => 'Lex']));
        });
    }

    protected function middleware(): array
    {
        return [
            \Silver\App\Middlewares\ErrorHandler::class,
            \Silver\Http\Middleware\SetLocale::class,
        ];
    }

    public function testDefaultLocaleApplies(): void
    {
        $this->get('/greet')->assertOk()->assertSee('Welcome, Lex!');
    }

    public function testQueryParamSwitchesLocaleAndPersistsToSession(): void
    {
        $this->get('/greet?lang=sl')->assertOk()->assertSee('Dobrodošel, Lex!');

        self::assertSame('sl', Session::get('lang'));
        self::assertSame('sl', app(Translator::class)->locale());
    }

    public function testSessionLocaleSticksOnLaterRequests(): void
    {
        Session::set('lang', 'sl');

        $this->get('/greet')->assertOk()->assertSee('Dobrodošel, Lex!');
    }

    public function testUnsupportedLocaleIsIgnored(): void
    {
        $this->get('/greet?lang=de')->assertOk()->assertSee('Welcome, Lex!');

        self::assertNotSame('de', app(Translator::class)->locale());
    }
}

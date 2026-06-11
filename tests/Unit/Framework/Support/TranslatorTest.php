<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Support;

use PHPUnit\Framework\TestCase;
use Silver\Support\Translator;

final class TranslatorTest extends TestCase
{
    private function translator(string $locale = 'en'): Translator
    {
        return new Translator(__DIR__ . '/fixtures/lang', $locale, 'en');
    }

    public function testGetReturnsALine(): void
    {
        self::assertSame('english only', $this->translator()->get('messages.only_en'));
    }

    public function testNestedDotPath(): void
    {
        self::assertSame('found me', $this->translator()->get('messages.nested.deep.line'));
    }

    public function testMissingKeyReturnsTheKeyItself(): void
    {
        self::assertSame('messages.nope', $this->translator()->get('messages.nope'));
        self::assertSame('nofile.x', $this->translator()->get('nofile.x'));
    }

    public function testHas(): void
    {
        self::assertTrue($this->translator()->has('messages.welcome'));
        self::assertFalse($this->translator()->has('messages.nope'));
    }

    public function testLocaleSwitchAndFallback(): void
    {
        $t = $this->translator('sl');

        self::assertSame('Dobrodošel, Lex!', $t->get('messages.welcome', ['name' => 'Lex']));
        // Key absent in sl → falls back to en.
        self::assertSame('english only', $t->get('messages.only_en'));

        $t->setLocale('en');
        self::assertSame('en', $t->locale());
        self::assertSame('Welcome, Lex!', $t->get('messages.welcome', ['name' => 'Lex']));
    }

    public function testInterpolationWithCaseVariants(): void
    {
        $t = $this->translator();

        self::assertSame('Welcome, Lex!', $t->get('messages.welcome', ['name' => 'Lex']));
        // :NAME → upper-cased value, :Greeting → ucfirst value.
        self::assertSame('LEX says Hello', $t->get('messages.shout', ['name' => 'lex', 'greeting' => 'hello']));
    }

    public function testChoiceSingularPlural(): void
    {
        $t = $this->translator();

        self::assertSame('one apple', $t->choice('messages.apples', 1));
        self::assertSame('3 apples', $t->choice('messages.apples', 3));
    }

    public function testChoiceWithExactAndRangeConditions(): void
    {
        $t = $this->translator();

        self::assertSame('no apples', $t->choice('messages.ranged', 0));
        self::assertSame('one apple', $t->choice('messages.ranged', 1));
        self::assertSame('a few apples', $t->choice('messages.ranged', 3));
        self::assertSame('many apples', $t->choice('messages.ranged', 99));
    }

    public function testTransHelpersResolveViaContainer(): void
    {
        \Silver\Core\App::refresh();
        \Silver\Core\Env::set('lang', [
            'path'     => __DIR__ . '/fixtures/lang',
            'locale'   => 'en',
            'fallback' => 'en',
        ]);

        self::assertSame('Welcome, Lex!', trans('messages.welcome', ['name' => 'Lex']));
        self::assertSame('3 apples', trans_choice('messages.apples', 3));

        \Silver\Core\App::refresh();
        new \ReflectionProperty(\Silver\Core\Env::class, 'envData')->setValue(null, null);
    }
}

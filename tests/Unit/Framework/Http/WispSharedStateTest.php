<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Http;

use PHPUnit\Framework\TestCase;
use Silver\Engine\Ghost\WispResponse;
use Silver\Http\View;

/**
 * Wisp polish — every page object carries an `errors` prop resolved
 * from the validation flash (field → first message, Inertia's shape),
 * and the errors()/old() helpers expose the same flash to classic
 * Ghost templates.
 */
final class WispSharedStateTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_GET = [];
        unset($_SERVER['HTTP_X_INERTIA'], $_SERVER['HTTP_X_INERTIA_PARTIAL_COMPONENT'], $_SERVER['HTTP_X_INERTIA_PARTIAL_DATA']);
        $_SERVER['REQUEST_URI'] = '/page';
        View::flushShared();
    }

    protected function tearDown(): void
    {
        View::flushShared();
        $_SESSION = [];
    }

    private function flashErrors(array $errors, array $old = []): void
    {
        // Simulate "flashed last request": rotateFlash promoted it.
        $_SESSION['old-flash']['_errors'] = $errors;
        if ($old !== []) {
            $_SESSION['old-flash']['_old'] = $old;
        }
    }

    // ---------- errors prop on the page object ----------

    public function testErrorsPropDefaultsToEmptyObject(): void
    {
        $page = new WispResponse('Users/Edit')->data();

        self::assertObjectHasProperty('errors', $page['props']);
        self::assertSame([], (array) $page['props']->errors);
    }

    public function testErrorsPropCarriesFirstMessagePerField(): void
    {
        $this->flashErrors([
            'email' => ['email is required.', 'email must be valid.'],
            'name'  => ['name is required.'],
        ]);

        $page = new WispResponse('Users/Edit')->data();

        self::assertSame(
            ['email' => 'email is required.', 'name' => 'name is required.'],
            (array) $page['props']->errors,
        );
    }

    public function testExplicitErrorsPropWins(): void
    {
        $this->flashErrors(['email' => ['flashed message']]);

        $page = new WispResponse('Users/Edit', ['errors' => ['custom' => 'mine']])->data();

        self::assertSame(['custom' => 'mine'], (array) $page['props']->errors);
    }

    // ---------- Ghost-side helpers ----------

    public function testErrorsHelperReturnsAllOrFirstForField(): void
    {
        $this->flashErrors(['email' => ['email is required.', 'second']]);

        self::assertSame(['email' => ['email is required.', 'second']], errors());
        self::assertSame('email is required.', errors('email'));
        self::assertNull(errors('name'));
    }

    public function testOldHelperReadsFlashedInput(): void
    {
        $this->flashErrors(['email' => ['x']], old: ['email' => 'typo@', 'name' => 'Lex']);

        self::assertSame('typo@', old('email'));
        self::assertSame('Lex', old('name'));
        self::assertSame('fallback', old('missing', 'fallback'));
    }

    public function testAuthHelperReturnsGuestGuardWithoutSession(): void
    {
        self::assertTrue(auth()->guest());
        self::assertNull(auth()->user());
    }
}

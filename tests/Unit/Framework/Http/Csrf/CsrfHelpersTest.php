<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Http\Csrf;

use PHPUnit\Framework\TestCase;
use Silver\Http\Csrf\TokenStore;

/**
 * The csrf_token() / csrf_field() helpers + the parseCsrf Ghost
 * directive all read from the same TokenStore. Reusing one token
 * across a request is fundamental to multi-form pages.
 */
final class CsrfHelpersTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testCsrfTokenReturnsStoreCurrent(): void
    {
        $store = app(TokenStore::class);
        $this->assertSame($store->current(), csrf_token());
    }

    public function testCsrfFieldEscapesAndContainsToken(): void
    {
        $token = csrf_token();
        $html  = csrf_field();
        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('value="' . $token . '"', $html);
    }
}

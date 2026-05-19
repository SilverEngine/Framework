<?php

namespace Tests\Unit\Framework\Core;

use PHPUnit\Framework\TestCase;
use Silver\Core\Env;
use Silver\Core\ErrorHandler;
use Silver\Exception\Exception as SilverException;

/**
 * Regression guard for the web error page: rich + self-contained in
 * debug, and — critically — zero info-leak in production. A future
 * change must not silently re-introduce the leak or re-couple the page
 * to the asset build.
 *
 * (The API JSON branch calls exit() so it can't run in-process; its
 * debug-gated trace is covered by manual/CLI verification instead.)
 */
class ErrorPageTest extends TestCase
{
    protected function tearDown(): void
    {
        // Don't let the APP_DEBUG toggle bleed into other tests.
        unset($_ENV['APP_DEBUG']);
        Env::construct(getcwd() . '/');
    }

    private function render(bool $debug, \Throwable $orig): string
    {
        $_ENV['APP_DEBUG'] = $debug ? 'true' : 'false';
        $_SERVER['REQUEST_URI'] = '/widgets/42';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        Env::construct(getcwd() . '/');

        $w = new SilverException($orig->getMessage(), (int) $orig->getCode(), $orig);
        $w->setFile($orig->getFile());
        $w->setLine($orig->getLine());

        return ErrorHandler::render($w)->render();
    }

    public function testDebugPageIsRichAndSelfContained(): void
    {
        $html = $this->render(true, new \RuntimeException('kaboom detail'));

        $this->assertStringContainsString('RuntimeException', $html);
        $this->assertStringContainsString('kaboom detail', $html);
        $this->assertStringContainsString('Stack trace', $html);
        $this->assertStringContainsString('Request', $html);
        $this->assertStringContainsString('<style>', $html);            // self-contained
        $this->assertStringNotContainsString('viteCss', $html);
        $this->assertStringNotContainsString('/build/', $html);         // no asset-build dependency
    }

    public function testProductionPageLeaksNothing(): void
    {
        $html = $this->render(false, new \RuntimeException('SECRET internal detail'));

        $this->assertStringContainsString('Server error', $html);
        $this->assertStringContainsString('Back home', $html);
        $this->assertStringContainsString('<style>', $html);
        $this->assertStringNotContainsString('SECRET internal detail', $html);
        $this->assertStringNotContainsString('RuntimeException', $html);
        $this->assertStringNotContainsString('Stack trace', $html);
    }
}

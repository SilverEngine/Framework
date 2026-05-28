<?php

declare(strict_types=1);

namespace Tests\Unit\Framework\Engine;

use PHPUnit\Framework\TestCase;
use Silver\Engine\Ghost\Template;

/**
 * Locks Ghost's three flavours of variable output side-by-side:
 *
 *   {{ $x }}   — htmlspecialchars-escaped (default)
 *   {{{ $x }}} — raw (legacy Mustache-style)
 *   {!! $x !!} — raw (Laravel/Blade-style, added so error pages and other
 *                 framework-rendered views can interpolate pre-built HTML
 *                 without the awkward triple-brace syntax)
 *
 * @ -prefixed `@{{ }}` stays untouched so frontend templating libraries
 * sharing the file format don't get clobbered.
 */
final class GhostVarsTest extends TestCase
{
    private function render(string $source, array $data = []): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ghost_var_') . '.ghost.php';
        file_put_contents($tmp, $source);
        try {
            return (new Template($tmp, $data))->render();
        } finally {
            @unlink($tmp);
        }
    }

    public function testDoubleBraceEscapesHtml(): void
    {
        $out = $this->render('{{ $x }}', ['x' => '<b>BOLD</b>']);
        $this->assertSame('&lt;b&gt;BOLD&lt;/b&gt;', $out);
    }

    public function testTripleBraceEmitsRawHtml(): void
    {
        $out = $this->render('{{{ $x }}}', ['x' => '<b>BOLD</b>']);
        $this->assertSame('<b>BOLD</b>', $out);
    }

    public function testBangBangEmitsRawHtml(): void
    {
        // Laravel-style raw output. Identical semantics to {{{ }}}.
        $out = $this->render('{!! $x !!}', ['x' => '<b>BOLD</b>']);
        $this->assertSame('<b>BOLD</b>', $out);
    }

    public function testBangBangAllowsWhitespaceAroundExpression(): void
    {
        $out = $this->render("{!!\n  \$x\n!!}", ['x' => '<hr>']);
        $this->assertSame('<hr>', $out);
    }

    public function testBangBangResolvesExpression(): void
    {
        // Anything PHP accepts in an `echo` position should work — array
        // dereferencing, concatenation, ternary, …
        $out = $this->render('{!! $x[0] . " " . strtoupper($y) !!}', [
            'x' => ['hi'],
            'y' => 'world',
        ]);
        $this->assertSame('hi WORLD', $out);
    }

    public function testAtBracedPlaceholderSurvivesUntouched(): void
    {
        // Vue/Blade-style escape — frontend frameworks expect the
        // expression text to round-trip unmodified.
        $out = $this->render('@{{ name }}');
        $this->assertSame('{{ name }}', $out);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Framework\Support;

use PHPUnit\Framework\TestCase;
use Silver\FileSystem\LocalFileSystem;
use Silver\Support\Scaffolder;

/**
 * Exercises the three types added during the CLI/Scaffolder unification:
 * view, helper, facade. Locks in stub shape + on-disk location so the
 * CLI delegation (`php silver g view foo` etc.) keeps working.
 */
final class ScaffolderTypesTest extends TestCase
{
    private Scaffolder $scaffolder;

    protected function setUp(): void
    {
        if (!defined('ROOT')) {
            define('ROOT', dirname(__DIR__, 4) . '/');
        }
        $this->scaffolder = new Scaffolder(new LocalFileSystem());
    }

    public function testTypesConstantIncludesAllNamedKinds(): void
    {
        $expected = [
            'page', 'controller', 'model', 'service', 'repository',
            'resource', 'middleware', 'provider', 'observer', 'dto', 'vo',
            'view', 'helper', 'facade',
        ];
        $this->assertSame($expected, Scaffolder::TYPES);
    }

    public function testCreateHelperWritesFile(): void
    {
        $result = $this->scaffolder->create('helper', 'Smoke');
        $this->assertSame('helper', $result['type']);
        $this->assertSame('Smoke', $result['name']);
        $this->assertSame(['app/Helpers/Smoke.php'], $result['created']);

        $path = ROOT . 'app/Helpers/Smoke.php';
        $this->assertFileExists($path);
        $src = (string) file_get_contents($path);
        $this->assertStringContainsString('namespace App\\Helpers', $src);
        $this->assertStringContainsString('final class Smoke', $src);
        $this->assertStringContainsString('declare(strict_types=1)', $src);

        $this->scaffolder->remove('helper', 'Smoke');
        $this->assertFileDoesNotExist($path);
    }

    public function testCreateFacadeWritesFile(): void
    {
        $result = $this->scaffolder->create('facade', 'Smoke');
        $this->assertSame('facade', $result['type']);
        $path = ROOT . 'app/Facades/Smoke.php';
        $this->assertFileExists($path);

        $src = (string) file_get_contents($path);
        $this->assertStringContainsString('namespace App\\Facades', $src);
        $this->assertStringContainsString('final class Smoke extends Facade', $src);
        $this->assertStringContainsString('SmokeService', $src);

        $this->scaffolder->remove('facade', 'Smoke');
        $this->assertFileDoesNotExist($path);
    }

    public function testCreateViewWritesGhostTemplate(): void
    {
        $result = $this->scaffolder->create('view', 'Smoke');
        $this->assertSame('view', $result['type']);
        // Views land under app/Resources/views/<lowercased>.ghost.tpl
        $path = ROOT . 'app/Resources/views/smoke.ghost.tpl';
        $this->assertFileExists($path);

        $src = (string) file_get_contents($path);
        $this->assertStringContainsString("extends('layouts.master')", $src);
        $this->assertStringContainsString('#set[content]', $src);
        $this->assertStringContainsString('#end', $src);

        $this->scaffolder->remove('view', 'Smoke');
        $this->assertFileDoesNotExist($path);
    }

    public function testCreateUnknownTypeThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->scaffolder->create('definitely-not-a-type', 'X');
    }
}

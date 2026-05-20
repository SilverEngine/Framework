<?php
declare(strict_types=1);

namespace Silver\Support;

use RuntimeException;
use Silver\Core\Env;
use Silver\Engine\Ghost\Compiler;
use Silver\FileSystem\FileSystem;

/**
 * Generates a Wisp page from a missing URL — controller + Vue component
 * + route. Triggered by the 404 dev page when `APP_ENV=local` and
 * `APP_DEBUG=true`. All file writes go through the {@see FileSystem}
 * service so hooks fire and the operation is decoratable.
 */
final class Scaffolder
{
    public function __construct(private FileSystem $fs)
    {
    }

    /**
     * @return array{name:string,url:string,created:list<string>}
     */
    public function scaffold(string $url, string $name): array
    {
        $this->guardEnv();

        $name = self::sanitiseName($name);
        $url  = '/' . ltrim($url, '/');

        $root = \ROOT;
        $controllerPath = $root . 'app/Controllers/' . $name . 'Controller.php';
        $vuePath        = $root . 'app/Resources/js/Pages/' . $name . '.vue';
        $routesPath     = $root . 'app/Routes/Web.php';

        $created = [];

        if (!$this->fs->isFile($controllerPath)) {
            $this->fs->write($controllerPath, $this->controllerStub($name));
            $created[] = 'app/Controllers/' . $name . 'Controller.php';
        }

        if (!$this->fs->isFile($vuePath)) {
            $this->fs->mkdir(dirname($vuePath));
            $this->fs->write($vuePath, $this->vueStub($name, $url));
            $created[] = 'app/Resources/js/Pages/' . $name . '.vue';
        }

        if ($this->appendRoute($routesPath, $url, $name)) {
            $created[] = 'app/Routes/Web.php (route added)';
        }

        // Bust any caches that would shadow the new artifacts. The route
        // cache may not exist (no `optimize` ever run) — guard so the
        // ErrorHandler doesn't promote the unlink notice to a fatal.
        $routeCache = $root . 'storage/cache/routes.php';
        if ($this->fs->isFile($routeCache)) {
            $this->fs->delete($routeCache);
        }
        Compiler::clear();

        return ['name' => $name, 'url' => $url, 'created' => $created];
    }

    /**
     * Suggest a controller name from a URL path. `/foo/bar-baz` -> `FooBarBaz`.
     * Path params (`{id}`) are dropped. Defaults to "Page" when empty.
     */
    public static function suggestName(string $urlPath): string
    {
        $parts = preg_split('/[^A-Za-z0-9]+/', $urlPath) ?: [];
        $parts = array_filter($parts, static fn (string $p): bool => $p !== '');
        $name = implode('', array_map('ucfirst', array_map('strtolower', $parts)));
        return $name === '' ? 'Page' : $name;
    }

    private static function sanitiseName(string $raw): string
    {
        $candidate = self::suggestName($raw);
        return preg_match('/^[A-Z][A-Za-z0-9]*$/', $candidate)
            ? $candidate
            : throw new RuntimeException("Invalid scaffold name: '{$raw}'.");
    }

    private function guardEnv(): void
    {
        if (Env::name() !== 'local' || !Env::get('debug')) {
            throw new RuntimeException(
                'Scaffolder requires APP_ENV=local and APP_DEBUG=true.',
            );
        }
    }

    private function controllerStub(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Controllers;

use Silver\\Core\\Controller;
use Silver\\Engine\\Ghost\\WispResponse;

final class {$name}Controller extends Controller
{
    public function __invoke(): WispResponse
    {
        return wisp('{$name}', [
            'message' => 'Scaffolded by SilverEngine.',
        ]);
    }
}

PHP;
    }

    private function vueStub(string $name, string $url): string
    {
        return <<<VUE
<script setup lang="ts">
defineProps<{
  message: string
}>()
</script>

<template>
  <div class="space-y-4">
    <p class="text-xs uppercase tracking-widest text-zinc-500">{$url}</p>
    <h1 class="text-3xl font-medium tracking-tight">{$name}</h1>
    <p class="text-zinc-500">{{ message }}</p>
  </div>
</template>

VUE;
    }

    /**
     * Append the `use` import and `Route::get(...)` line to Web.php if not
     * already present. Returns true when the file was actually changed.
     */
    private function appendRoute(string $path, string $url, string $name): bool
    {
        if (!$this->fs->isFile($path)) {
            return false;
        }
        $src = $this->fs->read($path);

        $useLine   = "use App\\Controllers\\{$name}Controller;";
        $routeLine = "\$route->get('{$url}', {$name}Controller::class);";

        if (str_contains($src, $routeLine)) {
            return false;   // already wired
        }

        // Insert the `use` after the last existing `use ...;` line.
        if (!str_contains($src, $useLine)) {
            $src = preg_replace(
                '/(^use [^\n]+;\n)(?!.*^use [^\n]+;\n)/ms',
                "$1" . $useLine . "\n",
                $src,
                1,
            ) ?? $src;
        }

        // Append the new route at end of file (preserve trailing newline).
        $src = rtrim($src, "\n") . "\n" . $routeLine . "\n";

        $this->fs->write($path, $src);
        return true;
    }
}

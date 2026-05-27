<?php
declare(strict_types=1);

namespace Silver\Support;

use RuntimeException;
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
    public const TYPES = [
        'page', 'controller', 'model', 'service', 'repository',
        'resource', 'middleware', 'provider', 'observer', 'dto', 'vo',
        'view', 'helper', 'facade',
    ];

    /** Composite types expand into multiple primitives. */
    private const COMPOSITES = [
        'resource' => ['model', 'repository', 'service', 'page'],
    ];

    public function __construct(private FileSystem $fs)
    {
    }

    /**
     * Dispatcher for `/create <type> <name>` from the web UI. `page` keeps
     * the legacy controller+vue+route flow; other types generate a single
     * class file under the conventional folder.
     *
     * @return array{type:string,name:string,url:?string,created:list<string>}
     */
    public function create(string $type, string $name): array
    {
        $type = strtolower(trim($type));
        $name = self::sanitiseName($name);

        if (!in_array($type, self::TYPES, true)) {
            throw new RuntimeException("Unknown type: '{$type}'. Use one of: " . implode(', ', self::TYPES));
        }

        // Composite: expand into primitives and aggregate results.
        if (isset(self::COMPOSITES[$type])) {
            $created = [];
            $url = null;
            foreach (self::COMPOSITES[$type] as $sub) {
                $sub_result = $this->create($sub, $name);
                $created = array_merge($created, $sub_result['created']);
                $url = $url ?? $sub_result['url'];
            }
            return ['type' => $type, 'name' => $name, 'url' => $url, 'created' => $created];
        }

        if ($type === 'page') {
            $url = '/' . strtolower($name);
            $result = $this->scaffold($url, $name);
            return ['type' => 'page', 'name' => $name, 'url' => $url, 'created' => $result['created']];
        }

        [$path, $relPath, $stub] = $this->plan($type, $name);

        if ($this->fs->isFile($path)) {
            throw new RuntimeException("{$relPath} already exists.");
        }

        $this->fs->mkdir(dirname($path));
        $this->fs->write($path, $stub);

        return ['type' => $type, 'name' => $name, 'url' => null, 'created' => [$relPath]];
    }

    /**
     * Inverse dispatcher for `/remove <type> <name>`.
     *
     * @return array{type:string,name:string,removed:list<string>}
     */
    public function remove(string $type, string $name): array
    {
        $type = strtolower(trim($type));
        $name = self::sanitiseName($name);

        if (!in_array($type, self::TYPES, true)) {
            throw new RuntimeException("Unknown type: '{$type}'. Use one of: " . implode(', ', self::TYPES));
        }

        if (isset(self::COMPOSITES[$type])) {
            $removed = [];
            $errors = [];
            foreach (self::COMPOSITES[$type] as $sub) {
                try {
                    $sub_result = $this->remove($sub, $name);
                    $removed = array_merge($removed, $sub_result['removed']);
                } catch (RuntimeException $e) {
                    $errors[] = $e->getMessage();
                }
            }
            if ($removed === []) {
                throw new RuntimeException(implode(' | ', $errors) ?: "Nothing to remove for {$type} '{$name}'.");
            }
            return ['type' => $type, 'name' => $name, 'removed' => $removed];
        }

        if ($type === 'page') {
            $url = '/' . strtolower($name);
            $result = $this->unscaffold($url);
            return ['type' => 'page', 'name' => $name, 'removed' => $result['removed']];
        }

        [$path, $relPath] = $this->plan($type, $name);

        if (!$this->fs->isFile($path)) {
            throw new RuntimeException("{$relPath} does not exist.");
        }

        $this->fs->delete($path);
        return ['type' => $type, 'name' => $name, 'removed' => [$relPath]];
    }

    /**
     * For non-page types: returns the absolute path, the project-relative
     * path (for messages), and the file stub.
     *
     * @return array{0:string,1:string,2:string}
     */
    private function plan(string $type, string $name): array
    {
        $root = \ROOT;
        return match ($type) {
            'controller' => [
                $root . 'app/Controllers/' . $name . 'Controller.php',
                'app/Controllers/' . $name . 'Controller.php',
                $this->controllerStub($name),
            ],
            'model' => [
                $root . 'app/Models/' . $name . '.php',
                'app/Models/' . $name . '.php',
                $this->modelStub($name),
            ],
            'service' => [
                $root . 'app/Services/' . $name . 'Service.php',
                'app/Services/' . $name . 'Service.php',
                $this->serviceStub($name),
            ],
            'repository' => [
                $root . 'app/Repositories/' . $name . 'Repository.php',
                'app/Repositories/' . $name . 'Repository.php',
                $this->repositoryStub($name),
            ],
            'middleware' => [
                $root . 'app/Middlewares/' . $name . '.php',
                'app/Middlewares/' . $name . '.php',
                $this->middlewareStub($name),
            ],
            'provider' => [
                $root . 'app/Providers/' . $name . 'Provider.php',
                'app/Providers/' . $name . 'Provider.php',
                $this->providerStub($name),
            ],
            'observer' => [
                $root . 'app/Observers/' . $name . 'Observer.php',
                'app/Observers/' . $name . 'Observer.php',
                $this->observerStub($name),
            ],
            'dto' => [
                $root . 'app/Dtos/' . $name . 'Dto.php',
                'app/Dtos/' . $name . 'Dto.php',
                $this->dtoStub($name),
            ],
            'vo' => [
                $root . 'app/ValueObjects/' . $name . '.php',
                'app/ValueObjects/' . $name . '.php',
                $this->voStub($name),
            ],
            'view' => [
                $root . 'app/Resources/views/' . strtolower($name) . '.ghost.tpl',
                'app/Resources/views/' . strtolower($name) . '.ghost.tpl',
                $this->viewStub($name),
            ],
            'helper' => [
                $root . 'app/Helpers/' . $name . '.php',
                'app/Helpers/' . $name . '.php',
                $this->helperStub($name),
            ],
            'facade' => [
                $root . 'app/Facades/' . $name . '.php',
                'app/Facades/' . $name . '.php',
                $this->facadeStub($name),
            ],
            default => throw new RuntimeException("No plan for type '{$type}'."),
        };
    }

    /**
     * @return array{name:string,url:string,created:list<string>}
     */
    public function scaffold(string $url, string $name): array
    {

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
     * Inverse of {@see scaffold()}. Removes the route line, controller
     * file, and Vue page for the given URL. The controller class name
     * is recovered from the route line itself so we never delete the
     * wrong file when the URL → name convention has been customised.
     *
     * @return array{name:string,url:string,removed:list<string>}
     */
    public function unscaffold(string $url): array
    {

        $url = '/' . ltrim($url, '/');
        $root = \ROOT;
        $routesPath = $root . 'app/Routes/Web.php';

        if (!$this->fs->isFile($routesPath)) {
            throw new RuntimeException('Routes file not found.');
        }

        $src = $this->fs->read($routesPath);
        $quoted = preg_quote($url, '/');
        $pattern = '/^\s*\$route->[a-z]+\(\s*[\'"]' . $quoted . '[\'"]\s*,\s*([A-Za-z_][A-Za-z0-9_]*)Controller::class[^\n]*;\s*\n/m';

        if (!preg_match($pattern, $src, $m)) {
            throw new RuntimeException("No route registered for '{$url}'.");
        }
        $name = $m[1];

        $controllerPath = $root . 'app/Controllers/' . $name . 'Controller.php';
        $vuePath        = $root . 'app/Resources/js/Pages/' . $name . '.vue';

        $removed = [];

        // Strip the route line.
        $newSrc = preg_replace($pattern, '', $src, 1) ?? $src;

        // Drop the `use` import if this was the only reference.
        if (!preg_match('/' . preg_quote($name, '/') . 'Controller::class/', $newSrc)) {
            $newSrc = preg_replace(
                '/^use App\\\\Controllers\\\\' . preg_quote($name, '/') . 'Controller;\s*\n/m',
                '',
                $newSrc,
                1,
            ) ?? $newSrc;
        }

        if ($newSrc !== $src) {
            $this->fs->write($routesPath, $newSrc);
            $removed[] = 'app/Routes/Web.php (route removed)';
        }

        if ($this->fs->isFile($controllerPath)) {
            $this->fs->delete($controllerPath);
            $removed[] = 'app/Controllers/' . $name . 'Controller.php';
        }

        if ($this->fs->isFile($vuePath)) {
            $this->fs->delete($vuePath);
            $removed[] = 'app/Resources/js/Pages/' . $name . '.vue';
        }

        $routeCache = $root . 'storage/cache/routes.php';
        if ($this->fs->isFile($routeCache)) {
            $this->fs->delete($routeCache);
        }
        Compiler::clear();

        return ['name' => $name, 'url' => $url, 'removed' => $removed];
    }

    /**
     * Studly-case a string for use as a PHP class name.
     *
     *   /foo/bar-baz  →  FooBarBaz       (URL slug from the web scaffolder)
     *   user_post     →  UserPost        (snake_case)
     *   smokeTest     →  SmokeTest       (camelCase — internal boundary preserved)
     *   SmokeModel    →  SmokeModel      (already studly — preserved, NOT flattened
     *                                     to "Smokemodel" the way the previous
     *                                     strtolower-then-ucfirst pipeline did)
     *
     * Defaults to "Page" when the input has no alphanumeric content.
     */
    public static function suggestName(string $input): string
    {
        // Split on non-alphanumerics AND at the lowercase→uppercase boundary so
        // existing internal capitals survive the round-trip.
        $parts = preg_split('/[^A-Za-z0-9]+|(?<=[a-z])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', $input) ?: [];
        $parts = array_filter($parts, static fn (string $p): bool => $p !== '');
        $name = implode('', array_map(
            static fn (string $p): string => ucfirst(strtolower($p)),
            $parts,
        ));
        return $name === '' ? 'Page' : $name;
    }

    private static function sanitiseName(string $raw): string
    {
        $candidate = self::suggestName($raw);
        return preg_match('/^[A-Z][A-Za-z0-9]*$/', $candidate)
            ? $candidate
            : throw new RuntimeException("Invalid scaffold name: '{$raw}'.");
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

    private function modelStub(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Models;

final class {$name}
{
}

PHP;
    }

    private function serviceStub(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Services;

use App\\Repositories\\{$name}Repository;

final class {$name}Service
{
    public function __construct(private readonly {$name}Repository \$repository)
    {
    }
}

PHP;
    }

    private function repositoryStub(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Repositories;

final class {$name}Repository
{
}

PHP;
    }

    private function middlewareStub(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Middlewares;

use Closure;
use Silver\\Core\\Contracts\\MiddlewareInterface;
use Silver\\Http\\Request;
use Silver\\Http\\Response;

final class {$name} implements MiddlewareInterface
{
    public function execute(Request \$request, Response \$response, Closure \$next): mixed
    {
        return \$next();
    }
}

PHP;
    }

    private function providerStub(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Providers;

use Silver\\Core\\Bootstrap\\ServiceProvider;
use Silver\\Http\\Request;
use Silver\\Http\\Response;

final class {$name}Provider implements ServiceProvider
{
    public function before(Request \$request, Response \$response): void
    {
    }

    public function after(Request \$request, Response \$response): void
    {
    }
}

PHP;
    }

    private function observerStub(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Observers;

final class {$name}Observer
{
    public function created(object \$model): void
    {
    }

    public function updated(object \$model): void
    {
    }

    public function deleted(object \$model): void
    {
    }
}

PHP;
    }

    private function dtoStub(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Dtos;

final readonly class {$name}Dto
{
    public function __construct(
        // public string \$field,
    ) {
    }
}

PHP;
    }

    private function voStub(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\ValueObjects;

final readonly class {$name}
{
    public function __construct(public string \$value)
    {
    }

    public function equals(self \$other): bool
    {
        return \$this->value === \$other->value;
    }
}

PHP;
    }

    private function vueStub(string $name, string $url): string
    {
        return <<<VUE
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: null })

defineProps<{
  message: string
}>()
</script>

<template>
  <div class="min-h-screen flex flex-col bg-white text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100 transition-colors">
    <header class="px-8 py-5 flex items-center justify-between text-sm">
      <Link href="/" class="flex items-center gap-2 font-medium tracking-tight hover:opacity-80 transition-opacity">
        <span class="inline-block size-2 bg-zinc-900 dark:bg-zinc-100"></span>
        SilverEngine
      </Link>
      <nav class="flex items-center gap-6 text-zinc-500 dark:text-zinc-400">
        <Link href="/" class="hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">Home</Link>
        <a href="https://silverengine.net/docs" class="hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">Docs</a>
      </nav>
    </header>

    <main class="flex-1 flex flex-col items-center px-6 py-16">
      <div class="w-full max-w-3xl">
        <p class="text-xs uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">{$url}</p>
        <h1 class="mt-3 text-5xl font-medium tracking-tight">{$name}</h1>
        <p class="mt-6 text-lg leading-relaxed text-zinc-600 dark:text-zinc-400 max-w-2xl">
          {{ message }}
        </p>

        <div class="mt-14">
          <Link
            href="/"
            class="text-xs font-medium px-4 py-2 rounded-full bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900 hover:opacity-90 transition-opacity"
          >
            ← Back home
          </Link>
        </div>
      </div>
    </main>

    <footer class="px-8 py-5 flex items-center justify-between text-xs text-zinc-400 dark:text-zinc-500 border-t border-zinc-100 dark:border-zinc-900">
      <span>&copy; {{ new Date().getFullYear() }} SilverEngine · MIT</span>
      <span>Built with Wisp.</span>
    </footer>
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

    private function viewStub(string $name): string
    {
        $title = $name;
        return <<<TPL
            {{ extends('layouts.master') }}

            #set[content]
                <h1>{$title}</h1>
                <p>Welcome to <b>{$title}</b>. Edit this file under
                <code>app/Resources/views/</code>.</p>
            #end

            TPL;
    }

    private function helperStub(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Helpers;

final class {$name}
{
}

PHP;
    }

    private function facadeStub(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\Facades;

use Silver\\Support\\Facade;

final class {$name} extends Facade
{
    protected static function getClass(): string
    {
        return \\App\\Services\\{$name}Service::class;
    }
}

PHP;
    }
}

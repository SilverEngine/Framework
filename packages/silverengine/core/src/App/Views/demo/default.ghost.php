<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="SilverEngine — a small PHP 8.5 framework you can read in one sitting.">
    <title>SilverEngine</title>
    {{ viteCss() }}
    <style>
        html { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        body { font-feature-settings: "ss01", "cv11"; }
    </style>
</head>
<body class="min-h-full bg-white text-zinc-900 antialiased">
    <div class="mx-auto max-w-3xl px-6">

        <header class="flex items-center justify-between py-6 text-sm">
            <a href="/" class="flex items-center gap-2 font-medium tracking-tight">
                <span class="inline-block size-2 bg-zinc-900"></span>
                SilverEngine
            </a>
            <nav class="flex items-center gap-6 text-zinc-500">
                <a href="https://silverengine.net/docs" class="hover:text-zinc-900 transition-colors">Docs</a>
                <a href="https://github.com/SilverEngine/Framework" class="hover:text-zinc-900 transition-colors">GitHub</a>
                <span class="text-zinc-400 tabular-nums">v1.0</span>
            </nav>
        </header>

        <main class="pt-20 pb-24">

            <h1 class="text-4xl sm:text-5xl font-medium tracking-tight leading-[1.05]">
                A PHP framework<br>
                <span class="text-zinc-400">you can read in one sitting.</span>
            </h1>

            <p class="mt-8 max-w-xl text-zinc-600 leading-relaxed">
                SilverEngine is a small, opinionated PHP 8.5 framework. Two runtime
                dependencies. Routing, container, persistence, errors, and a Vue 3
                bridge — all in one repository.
            </p>

            <div class="mt-10 flex items-center gap-8 text-sm">
                <a href="https://silverengine.net/docs" class="font-medium border-b border-zinc-900 pb-0.5 hover:text-zinc-500 hover:border-zinc-300 transition-colors">
                    Get started &rarr;
                </a>
                <a href="https://github.com/SilverEngine/Framework" class="font-medium text-zinc-500 hover:text-zinc-900 transition-colors">
                    GitHub &rarr;
                </a>
            </div>

            <pre class="mt-12 text-[13px] leading-relaxed bg-zinc-50 border border-zinc-200 px-4 py-3 text-zinc-700 overflow-x-auto"><code>$ composer create-project silverengine/framework app</code></pre>

            <hr class="mt-24 border-zinc-200">

            <section class="mt-16">
                <dl class="grid sm:grid-cols-2 gap-x-12 gap-y-10">
                    <?php
                    $features = [
                        ['Routing',   'A plain ordered list of route files. The first entry is the framework, then yours. No discovery, no magic.'],
                        ['Container', 'Real IoC with autowiring, singletons, and constructor injection into controllers and middleware.'],
                        ['Wisp',      'Server-driven Vue. Return a page object from a controller. No client router, no separate API.'],
                        ['Database',  'Lazy PDO, nested transactions with savepoints, and a typed dialect for SQLite, MySQL, Postgres.'],
                        ['Errors',    'Self-contained 404 and 500 pages with inline CSS. They render even when the rest of the app is broken.'],
                        ['Recorder',  'Every request timed and persisted to disk in development. Browse the timeline at /debug.'],
                    ];
                    foreach ($features as [$title, $body]):
                    ?>
                    <div>
                        <dt class="text-sm font-medium text-zinc-900"><?= $title ?></dt>
                        <dd class="mt-1.5 text-sm text-zinc-500 leading-relaxed"><?= $body ?></dd>
                    </div>
                    <?php endforeach; ?>
                </dl>
            </section>

            <hr class="mt-24 border-zinc-200">

            <section class="mt-16">
                <h2 class="text-sm font-medium text-zinc-900">A route. A controller. That's all.</h2>
                <p class="mt-1.5 text-sm text-zinc-500 max-w-xl leading-relaxed">
                    Return a Ghost template for server rendering, or a Wisp page for a Vue
                    component. Same controller, same request lifecycle.
                </p>
<pre class="mt-6 text-[13px] leading-relaxed bg-zinc-50 border border-zinc-200 px-4 py-3 text-zinc-700 overflow-x-auto"><code>// app/Routes/Web.php
$route->get('/', WelcomeController::class);
// or with a specific method:
$route->get('/users', [UserController::class, 'index']);

// app/Controllers/WelcomeController.php
final class WelcomeController extends Controller
{
    public function __invoke(): Response
    {
        return wisp('Welcome', [
            'message' => 'Hello, world.',
        ]);
    }
}</code></pre>
            </section>

        </main>

        <footer class="border-t border-zinc-200 py-6 flex items-center justify-between text-xs text-zinc-400 tabular-nums">
            <span>&copy; <?= date('Y') ?> SilverEngine &middot; MIT</span>
            <span class="flex items-center gap-5">
                <span>{{ $_branch_ ?: 'detached' }}</span>
                <span>{{ $routes ?? '—' }} routes</span>
                <span>{{ $serverTime ?? '—' }}</span>
                <span><?= defined('APP_START') ? number_format((hrtime(true) - APP_START) / 1e6, 1) . 'ms' : '—' ?></span>
                <span>php <?= PHP_VERSION ?></span>
            </span>
        </footer>

    </div>
</body>
</html>

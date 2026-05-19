<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SilverEngine Framework</title>
    {{ viteCss() }}
</head>
<body class="h-full bg-slate-950 text-slate-100 antialiased">
    <div class="min-h-full flex flex-col">
        <header class="flex items-center justify-between px-6 py-4 text-xs text-slate-500 border-b border-slate-800">
            <span><b class="text-slate-300">Branch:</b> {{ $_branch_ ?: 'No git found!' }}</span>
            <span><b class="text-slate-300">Load:</b> <?php echo defined('APP_START') ? number_format((hrtime(true) - APP_START) / 1e6, 2) . 'ms' : 'N/A'; ?></span>
        </header>

        <main class="flex-1 flex flex-col items-center justify-center gap-6 px-6 text-center">
            <div class="size-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-sky-400 grid place-items-center text-2xl font-black text-slate-950">S</div>
            <h1 class="text-5xl font-bold tracking-tight">SilverEngine</h1>
            <p class="text-slate-400">Zero-dependency PHP framework — now with Vite + Vue 3 + Tailwind.</p>
            <nav class="flex flex-wrap justify-center gap-3 pt-2">
                <a href="/demo" class="px-4 py-2 rounded-md bg-slate-800 hover:bg-slate-700 transition-colors">Welcome (Ghost)</a>
                <a href="/wisp-demo" class="px-4 py-2 rounded-md bg-indigo-600 hover:bg-indigo-500 transition-colors">Wisp demo (Vue)</a>
                <a href="https://silverengine.net/docs" class="px-4 py-2 rounded-md ring-1 ring-slate-700 hover:bg-slate-800 transition-colors">Docs</a>
            </nav>
        </main>

        <footer class="px-6 py-4 text-center text-xs text-slate-600 border-t border-slate-800">
            SilverEngine &copy; 2017&ndash;2026
        </footer>
    </div>
</body>
</html>

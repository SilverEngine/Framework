<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }}</title>
    {{ viteCss() }}
</head>
<body class="h-full bg-slate-950 text-slate-100 antialiased">
    <main class="min-h-full flex flex-col items-center justify-center gap-6 px-6 text-center">
        <p class="text-xs uppercase tracking-[0.3em] text-slate-500">{{ $appName }}</p>
        <h1 class="text-5xl font-bold tracking-tight">Welcome</h1>
        <p class="max-w-md text-slate-400">
            Classic Ghost view, styled with Tailwind via the shared
            <code class="text-indigo-400">viteCss</code> pipeline.
        </p>
        <div class="flex gap-3">
            <a href="/" class="px-5 py-2 rounded-md bg-slate-800 hover:bg-slate-700 transition-colors">Home</a>
            <a href="/wisp-demo" class="px-5 py-2 rounded-md bg-indigo-600 hover:bg-indigo-500 transition-colors">Wisp demo</a>
        </div>
    </main>
</body>
</html>

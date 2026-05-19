<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 — Not Found</title>
    {{ viteCss() }}
</head>
<body class="h-full bg-slate-950 text-slate-100 antialiased">
    <main class="min-h-full flex flex-col items-center justify-center gap-4 px-6 text-center">
        <p class="text-7xl font-black text-slate-700">404</p>
        <h1 class="text-2xl font-semibold">Page not found</h1>
        <p class="max-w-md text-slate-400">
            #if($debug)
                {{ $message ?: 'The requested page could not be found.' }}
            #else
                The requested page could not be found.
            #endif
        </p>
        <a href="/" class="mt-2 px-5 py-2 rounded-md bg-indigo-600 hover:bg-indigo-500 transition-colors">Back home</a>
    </main>
</body>
</html>

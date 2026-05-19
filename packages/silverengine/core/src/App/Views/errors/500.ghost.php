<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 — Server Error</title>
    {{ viteCss() }}
</head>
<body class="h-full bg-slate-950 text-slate-100 antialiased">
<main class="min-h-full flex flex-col items-center justify-center gap-6 px-6 py-12">

    <div class="text-center">
        <p class="text-7xl font-black text-rose-500/40">500</p>
        <h1 class="text-2xl font-semibold mt-1">Server error</h1>
        #if($debug)
            <p class="text-xs uppercase tracking-widest text-slate-500 mt-2">
                Branch: {{ ucfirst($_branch_ ?? '') ?: 'n/a' }}
            </p>
        #endif
    </div>

    #if($debug)
        <div class="w-full max-w-3xl space-y-4">
            <div class="rounded-lg bg-rose-950/40 ring-1 ring-rose-900 px-4 py-3">
                <p class="font-semibold text-rose-300">{{ $message }}</p>
                <p class="text-sm text-slate-400 mt-1">
                    {{ $file ?: $class }} <span class="text-slate-500">on line</span> {{ $line }}
                </p>
            </div>

            <pre class="rounded-lg bg-slate-900 ring-1 ring-slate-800 p-4 overflow-auto text-sm leading-relaxed"><code>{{ $code_around }}</code></pre>

            <div class="rounded-lg bg-slate-900 ring-1 ring-slate-800 divide-y divide-slate-800">
                #foreach($back_trace as $bt)
                    #if(isset($bt['file']))
                        <div class="flex items-center justify-between gap-4 px-4 py-2 text-sm">
                            <span class="truncate text-slate-300">{{ $bt['file'] }}</span>
                            <span class="shrink-0 text-slate-500">line {{ $bt['line'] ?? '?' }}</span>
                        </div>
                    #endif
                #endforeach
            </div>
        </div>
    #else
        <p class="max-w-md text-center text-slate-400">
            Something went wrong on our end. Please try again later.
        </p>
        <a href="/" class="px-5 py-2 rounded-md bg-indigo-600 hover:bg-indigo-500 transition-colors">Back home</a>
    #endif

</main>
</body>
</html>

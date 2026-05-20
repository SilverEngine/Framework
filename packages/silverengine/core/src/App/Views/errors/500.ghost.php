<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $debug ? $class : '500 — Server Error' }}</title>
    <style>
        /* Self-contained by design: the error page must render even when
           the app, the asset build or the database is broken — it never
           depends on Vite/Tailwind. Palette mirrors the slate/rose theme. */
        :root { color-scheme: dark }
        * { box-sizing: border-box }
        body { margin:0; padding:2.5rem 1.25rem; background:#020617; color:#e2e8f0;
               font:14px/1.6 ui-sans-serif,system-ui,-apple-system,sans-serif }
        .wrap { max-width:60rem; margin:0 auto }
        .center { min-height:80vh; display:flex; flex-direction:column;
                  align-items:center; justify-content:center; gap:.6rem; text-align:center }
        .code { font-size:5rem; font-weight:900; color:#3f3f46; margin:0; line-height:1 }
        .badge { display:inline-block; font:600 11px/1 ui-sans-serif; letter-spacing:.15em;
                 text-transform:uppercase; color:#fb7185; background:rgba(244,63,94,.12);
                 border:1px solid rgba(244,63,94,.35); padding:.4rem .6rem;
                 border-radius:.4rem; margin-bottom:1rem }
        h1 { color:#fda4af; font-size:1.35rem; margin:0 0 .35rem }
        .msg { color:#f1f5f9; margin:0 0 .25rem; font-size:1rem }
        .loc { color:#7dd3fc; font:13px ui-monospace,Menlo,Consolas,monospace; margin-bottom:1.5rem }
        h2 { font-size:.78rem; text-transform:uppercase; letter-spacing:.12em;
             color:#a78bfa; margin:1.75rem 0 .5rem }
        pre { font:13px/1.6 ui-monospace,Menlo,Consolas,monospace; background:#0f172a;
              border:1px solid #1e293b; padding:1rem; border-radius:.6rem;
              overflow:auto; margin:0 }
        table.req { width:100%; border-collapse:collapse; font-size:13px;
                    background:#0f172a; border:1px solid #1e293b; border-radius:.6rem; overflow:hidden }
        table.req td { padding:.5rem .75rem; border-top:1px solid #1e293b; vertical-align:top }
        table.req td:first-child { color:#94a3b8; width:9rem; text-transform:uppercase;
                                   font-size:11px; letter-spacing:.08em }
        table.req tr:first-child td { border-top:0 }
        .frames { background:#0f172a; border:1px solid #1e293b; border-radius:.6rem }
        .frame { display:flex; justify-content:space-between; gap:1rem;
                 padding:.55rem .9rem; border-top:1px solid #1e293b; font-size:13px }
        .frame:first-child { border-top:0 }
        .frame .where { color:#e2e8f0; font:13px ui-monospace,monospace; word-break:break-all }
        .frame .at { color:#64748b; font:12px ui-monospace,monospace; white-space:nowrap }
        a.home { margin-top:.75rem; display:inline-block; padding:.55rem 1.25rem;
                 border-radius:.5rem; background:#4f46e5; color:#fff; text-decoration:none }
        a.home:hover { background:#6366f1 }
        .muted { color:#94a3b8; max-width:32rem }
    </style>
</head>
<body>

#if($debug)
    <div class="wrap">
        <span class="badge">Unhandled exception</span>
        <h1>{{ $class }}</h1>
        <p class="msg">{{ $message }}</p>
        <div class="loc">{{ $file }}:{{ $line }}</div>

        <h2>Request</h2>
        <table class="req">
            <tr><td>Method</td><td>{{ strtoupper($request['method'] ?? '-') }}</td></tr>
            <tr><td>URI</td><td>{{ $request['uri'] ?? '-' }}</td></tr>
            <tr><td>Route</td><td>{{ $request['route'] ?? '-' }}</td></tr>
            <tr><td>Client</td><td>{{ $request['ip'] ?? '-' }}</td></tr>
            #if(!empty($request['query']))
                <tr><td>Query</td><td>{{ json_encode($request['query']) }}</td></tr>
            #endif
            #if(!empty($request['input']))
                <tr><td>Input</td><td>{{ json_encode($request['input']) }}</td></tr>
            #endif
        </table>

        <h2>Source</h2>
        <pre>{{ $code_around }}</pre>

        <h2>Stack trace</h2>
        <div class="frames">
            #foreach($frames as $f)
                <div class="frame">
                    <span class="where">{{ $f['where'] }}</span>
                    <span class="at">{{ $f['file'] }}:{{ $f['line'] }}</span>
                </div>
            #endforeach
            #if(empty($frames))
                <div class="frame"><span class="where">{main}</span></div>
            #endif
        </div>
    </div>
#else
    <div class="center">
        <p class="code">500</p>
        <h1>Server error</h1>
        <p class="muted">Something went wrong on our end. Please try again later.</p>
        <a class="home" href="/">Back home</a>
    </div>
#endif

</body>
</html>

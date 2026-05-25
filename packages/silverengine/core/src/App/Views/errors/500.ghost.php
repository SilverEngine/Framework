<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $debug ? $class : '500 — Server Error' }}</title>
    <style>
        /* Self-contained by design: this view must render even when the
           app, the asset build or the database is broken. Never depends
           on Vite/Tailwind. Minimal monochrome to match the rest. */
        :root { color-scheme: light }
        * { box-sizing: border-box }
        html, body { margin:0; padding:0 }
        body { background:#fff; color:#18181b; font:15px/1.55 ui-sans-serif,system-ui,
               -apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
               -webkit-font-smoothing:antialiased }
        .wrap { max-width:54rem; margin:0 auto; padding:4rem 1.5rem 5rem }
        .eyebrow { font-size:12px; letter-spacing:.12em; text-transform:uppercase; color:#dc2626; margin:0 0 1rem }
        h1 { font-size:1.8rem; font-weight:500; letter-spacing:-.01em; margin:0 0 .35rem; line-height:1.15 }
        p.msg { color:#27272a; font-size:1.05rem; margin:0 0 .35rem }
        .loc { color:#71717a; font:13px ui-monospace,Menlo,Consolas,monospace; margin:0 0 2rem }
        h2 { font-size:12px; text-transform:uppercase; letter-spacing:.12em; color:#a1a1aa; margin:2rem 0 .6rem; font-weight:600 }
        pre { font:13px/1.55 ui-monospace,Menlo,Consolas,monospace; background:#fafafa;
              border:1px solid #e4e4e7; padding:.85rem 1rem; border-radius:4px;
              overflow:auto; margin:0; color:#27272a }
        table.req { width:100%; border-collapse:collapse; font-size:13.5px;
                    background:#fafafa; border:1px solid #e4e4e7; border-radius:4px; overflow:hidden }
        table.req td { padding:.5rem .85rem; border-top:1px solid #e4e4e7; vertical-align:top }
        table.req td:first-child { color:#a1a1aa; width:7.5rem; font-size:11.5px;
                                   text-transform:uppercase; letter-spacing:.08em }
        table.req tr:first-child td { border-top:0 }
        .frames { background:#fafafa; border:1px solid #e4e4e7; border-radius:4px }
        .frame { display:flex; justify-content:space-between; gap:1rem;
                 padding:.5rem .85rem; border-top:1px solid #e4e4e7; font-size:13px }
        .frame:first-child { border-top:0 }
        .frame .where { color:#27272a; font:13px ui-monospace,monospace; word-break:break-all }
        .frame .at { color:#a1a1aa; font:12.5px ui-monospace,monospace; white-space:nowrap }
        /* Production view */
        .center { min-height:100vh; display:flex; flex-direction:column;
                  align-items:center; justify-content:center; gap:.75rem; padding:2rem; text-align:center }
        .code { font-size:5rem; font-weight:600; color:#e4e4e7; margin:0; line-height:1; letter-spacing:-.02em }
        .center h1 { font-size:1.35rem }
        .center p { color:#71717a; max-width:24rem; margin:.25rem 0 1.25rem }
        a.home { font-size:14px; font-weight:500; color:#18181b; text-decoration:none;
                 border-bottom:1px solid #18181b; padding-bottom:1px }
        a.home:hover { color:#71717a; border-color:#d4d4d8 }
    </style>
</head>
<body>

#if($debug)
    <div class="wrap">
        <p class="eyebrow">Unhandled exception</p>
        <h1>{{ $class }}</h1>
        <p class="msg">{{ $message }}</p>
        <p class="loc">{{ $file }}:{{ $line }}</p>

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
        <p>Something went wrong on our end. Please try again later.</p>
        <a class="home" href="/">Back home</a>
    </div>
#endif

</body>
</html>

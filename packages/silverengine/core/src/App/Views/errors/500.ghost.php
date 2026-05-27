<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $debug ? $class : '500 — Server Error' }}</title>
    <style>
        /* Self-contained by design: this view must render even when the
           app, the asset build or the database is broken. Never depends
           on Vite/Tailwind. Inline only. */
        :root {
            color-scheme: light dark;
            --bg: #ffffff;
            --fg: #18181b;
            --muted: #71717a;
            --muted-2: #a1a1aa;
            --border: #e4e4e7;
            --border-soft: #f4f4f5;
            --card: #fafafa;
            --code-bg: #fafafa;
            --accent: #dc2626;
            --accent-soft: rgba(220,38,38,.08);
            --hit-bg: rgba(220,38,38,.10);
            --hit-rail: #dc2626;
            --kbd: #18181b;
            --kbd-bg: #f4f4f5;
            --link: #2563eb;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #09090b;
                --fg: #fafafa;
                --muted: #a1a1aa;
                --muted-2: #71717a;
                --border: #27272a;
                --border-soft: #1c1c1f;
                --card: #131316;
                --code-bg: #0c0c0e;
                --accent: #f87171;
                --accent-soft: rgba(248,113,113,.08);
                --hit-bg: rgba(248,113,113,.10);
                --hit-rail: #f87171;
                --kbd: #fafafa;
                --kbd-bg: #27272a;
                --link: #60a5fa;
            }
        }
        * { box-sizing: border-box }
        html, body { margin: 0; padding: 0 }
        body { background: var(--bg); color: var(--fg);
               font: 14px/1.55 ui-sans-serif, system-ui, -apple-system,
                     "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
               -webkit-font-smoothing: antialiased }
        a { color: var(--link); text-decoration: none }
        a:hover { text-decoration: underline }
        .mono { font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace }

        .wrap { max-width: 76rem; margin: 0 auto; padding: 2rem 1.5rem 4rem }

        /* Header */
        header { display: flex; align-items: flex-start; justify-content: space-between;
                 gap: 2rem; padding-bottom: 1.5rem; margin-bottom: 2rem;
                 border-bottom: 1px solid var(--border-soft) }
        .err-eyebrow { display: inline-flex; align-items: center; gap: .5rem;
                       font: 600 11px/1 ui-sans-serif; letter-spacing: .12em;
                       text-transform: uppercase; color: white; background: var(--accent);
                       padding: .4rem .65rem; border-radius: 999px; margin-bottom: .85rem }
        .err-eyebrow .pulse { width: .45rem; height: .45rem; border-radius: 50%;
                              background: currentColor; animation: pulse 1.8s ease-in-out infinite }
        @keyframes pulse { 0%,100% { opacity: 1 } 50% { opacity: .4 } }
        h1.err-class { font-size: 1.65rem; font-weight: 500; letter-spacing: -.015em;
                       margin: 0 0 .35rem; line-height: 1.2; color: var(--fg) }
        p.err-msg { color: var(--fg); font-size: 1.05rem; margin: 0 0 .5rem;
                    word-break: break-word }
        .err-loc { font-family: ui-monospace, monospace; font-size: 13px;
                   color: var(--muted); word-break: break-all }
        .err-loc a { color: var(--muted); border-bottom: 1px dotted var(--border) }
        .err-loc a:hover { color: var(--fg); text-decoration: none }

        .meta-pills { display: flex; gap: .4rem; flex-wrap: wrap; align-items: flex-start;
                      flex-shrink: 0; max-width: 18rem; justify-content: flex-end }
        .pill { display: inline-flex; align-items: center; gap: .35rem;
                font: 500 11px/1 ui-monospace, monospace; padding: .35rem .55rem;
                border: 1px solid var(--border); border-radius: 999px;
                background: var(--card); color: var(--muted) }
        .pill b { color: var(--fg); font-weight: 600 }

        /* Sections */
        section { margin: 2.25rem 0 }
        h2 { font-size: 11px; text-transform: uppercase; letter-spacing: .15em;
             color: var(--muted); margin: 0 0 .75rem; font-weight: 600;
             display: flex; align-items: baseline; justify-content: space-between }
        h2 .meta { font-weight: 400; letter-spacing: 0; text-transform: none;
                   color: var(--muted-2); font-size: 11px }

        /* Source viewer */
        .src { border: 1px solid var(--border); border-radius: .65rem;
               background: var(--code-bg); overflow: hidden }
        .src-head { display: flex; align-items: center; justify-content: space-between;
                    padding: .5rem .85rem; border-bottom: 1px solid var(--border);
                    background: var(--card); font: 12px ui-monospace, monospace;
                    color: var(--muted) }
        .src-head .path { white-space: nowrap; overflow: hidden;
                          text-overflow: ellipsis; min-width: 0; flex: 1 }
        .src-head .ide { font-size: 11px; color: var(--muted); margin-left: 1rem;
                         flex-shrink: 0 }
        .src-lines { margin: 0; padding: 0; list-style: none;
                     font: 13px/1.55 ui-monospace, "SF Mono", Menlo, Consolas, monospace;
                     counter-reset: none }
        .src-lines li { display: grid; grid-template-columns: 3.5rem 1fr;
                        column-gap: 0; padding: 0; border-left: 3px solid transparent }
        .src-lines li .n { color: var(--muted-2); text-align: right;
                           padding: .05rem .85rem .05rem 0; user-select: none;
                           font-size: 12px }
        .src-lines li .c { padding: .05rem .85rem;
                           white-space: pre; overflow-x: auto; color: var(--fg) }
        .src-lines li.hit { background: var(--hit-bg); border-left-color: var(--hit-rail) }
        .src-lines li.hit .n { color: var(--accent); font-weight: 600 }
        .src-lines li.hit .c { color: var(--fg) }

        .trace { border: 1px solid var(--border); border-radius: .65rem;
                 background: var(--card); overflow: hidden }
        .trace details { border-top: 1px solid var(--border-soft) }
        .trace details:first-child { border-top: 0 }
        .trace summary {
            display: grid; grid-template-columns: 4.5rem 1fr auto; align-items: center;
            gap: .85rem; padding: .55rem .85rem; cursor: pointer; user-select: none;
            list-style: none;
        }
        .trace summary::-webkit-details-marker { display: none }
        .trace summary::after { content: '⌄'; color: var(--muted-2); font-size: 12px;
                                transition: transform .15s }
        .trace details[open] summary::after { transform: rotate(180deg) }
        .trace summary:hover { background: var(--accent-soft) }
        .kind { display: inline-block; font: 600 9.5px/1 ui-monospace, monospace;
                letter-spacing: .08em; text-transform: uppercase;
                padding: .25rem .4rem; border-radius: .25rem; text-align: center }
        .kind.app       { background: rgba(22,163,74,.12); color: #16a34a }
        .kind.framework { background: rgba(99,102,241,.14); color: #6366f1 }
        .kind.vendor    { background: rgba(120,113,108,.18); color: #78716c }
        .kind.internal  { background: rgba(168,162,158,.14); color: #a8a29e }
        .where { font: 13px ui-monospace, monospace; color: var(--fg);
                 word-break: break-all; min-width: 0 }
        .at { font: 12px ui-monospace, monospace; color: var(--muted);
              white-space: nowrap; text-align: right }
        .trace details > .panel { padding: 0; border-top: 1px solid var(--border-soft);
                                  background: var(--code-bg) }
        .trace details > .panel .src-head { border-radius: 0; border: none;
                                            border-bottom: 1px solid var(--border-soft) }

        /* Hide vendor frames by default; user can flip the toggle. */
        #show-vendor { display: none }
        body:not(.vendor-on) .trace details.kind-vendor   { display: none }
        body:not(.vendor-on) .trace details.kind-internal { display: none }
        .toggle-row { display: flex; gap: .5rem; align-items: center;
                      padding: .45rem .85rem; background: var(--code-bg);
                      border-top: 1px solid var(--border-soft);
                      font-size: 12px; color: var(--muted) }
        .toggle-row label { cursor: pointer; user-select: none }
        .toggle-row label input { vertical-align: middle; margin: 0 .35rem 0 0 }

        /* Two-column grid for request + env */
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem }
        @media (max-width: 720px) { .grid { grid-template-columns: 1fr } }

        /* Key/value tables */
        table.kv { width: 100%; border-collapse: collapse; font-size: 13px;
                   border: 1px solid var(--border); border-radius: .65rem;
                   background: var(--card); overflow: hidden }
        table.kv td { padding: .4rem .75rem; border-top: 1px solid var(--border-soft);
                      vertical-align: top; word-break: break-word }
        table.kv tr:first-child td { border-top: 0 }
        table.kv td:first-child { color: var(--muted); width: 8rem; font-size: 11px;
                                  text-transform: uppercase; letter-spacing: .08em }

        /* Previous exception chain */
        .prev { padding: .65rem .85rem; border: 1px solid var(--border);
                background: var(--card); border-radius: .5rem; margin-bottom: .35rem;
                font-size: 13px }
        .prev .pclass { font-weight: 600; color: var(--accent) }
        .prev .pmsg { color: var(--fg); margin-top: .1rem }
        .prev .ploc { color: var(--muted); font: 12px ui-monospace, monospace;
                      margin-top: .15rem }

        /* Production view */
        .center { min-height: 100vh; display: flex; flex-direction: column;
                  align-items: center; justify-content: center; gap: .75rem;
                  padding: 2rem; text-align: center }
        .center .code { font-size: 5rem; font-weight: 600; color: var(--border);
                        margin: 0; line-height: 1; letter-spacing: -.02em }
        .center h1 { font-size: 1.35rem }
        .center p { color: var(--muted); max-width: 24rem; margin: .25rem 0 1.25rem }
        a.home { font-size: 14px; font-weight: 500; color: var(--fg);
                 border-bottom: 1px solid currentColor; padding-bottom: 1px }
        a.home:hover { color: var(--muted); border-color: var(--border);
                       text-decoration: none }
    </style>
</head>
<body>

#if($debug)
    <div class="wrap">
        <header>
            <div style="min-width:0; flex:1">
                <span class="err-eyebrow"><span class="pulse"></span>Unhandled exception</span>
                <h1 class="err-class">{{ $class }}</h1>
                <p class="err-msg">{{ $message }}</p>
                <div class="err-loc">
                    #if($source_ide)
                        <a href="{{ $source_ide }}">{{ $rel_file ?? $file }}:{{ $line }}</a>
                    #else
                        {{ $rel_file ?? $file }}:{{ $line }}
                    #endif
                </div>
            </div>
            <div class="meta-pills">
                <span class="pill">env <b>{{ $env['name'] }}</b></span>
                <span class="pill">php <b>{{ $env['php'] }}</b></span>
                #if($env['opcache'])
                    <span class="pill">opcache <b>on</b></span>
                #endif
                <span class="pill">mem <b>{{ $env['mem_peak'] }}MB</b></span>
            </div>
        </header>

        #if(count($previous))
            <section>
                <h2>Caused by</h2>
                #foreach($previous as $p)
                    <div class="prev">
                        <span class="pclass">{{ $p['class'] }}</span>
                        <div class="pmsg">{{ $p['message'] }}</div>
                        <div class="ploc">{{ $p['file'] }}:{{ $p['line'] }}</div>
                    </div>
                #endforeach
            </section>
        #endif

        <section>
            <h2>
                Source
                <span class="meta">{{ $rel_file ?? $file }} · line {{ $line }}</span>
            </h2>
            <div class="src">
                <div class="src-head">
                    <span class="path">{{ $rel_file ?? $file }}</span>
                    #if($source_ide)
                        <a class="ide" href="{{ $source_ide }}">open in editor →</a>
                    #endif
                </div>
                <ol class="src-lines">
                    #foreach($source as $row)
                        <li class="{{ $row['hit'] ? 'hit' : '' }}">
                            <span class="n">{{ $row['n'] }}</span><span class="c">{{ $row['text'] }}</span>
                        </li>
                    #endforeach
                </ol>
            </div>
        </section>

        <section>
            <h2>
                Stack trace
                <span class="meta">{{ count($frames) }} frames</span>
            </h2>
            <input id="show-vendor" type="checkbox">
            <div class="trace">
                #foreach($frames as $f)
                    <details class="kind-{{ $f['kind'] }}">
                        <summary>
                            <span class="kind {{ $f['kind'] }}">{{ $f['kind'] }}</span>
                            <span class="where">{{ $f['where'] }}</span>
                            <span class="at">
                                #if($f['ide'])
                                    <a href="{{ $f['ide'] }}">{{ $f['rel'] }}:{{ $f['line'] }}</a>
                                #else
                                    {{ $f['rel'] }}{{ $f['line'] !== '' ? ':' . $f['line'] : '' }}
                                #endif
                            </span>
                        </summary>
                        #if(!empty($f['snippet']))
                            <div class="panel">
                                <ol class="src-lines">
                                    #foreach($f['snippet'] as $row)
                                        <li class="{{ $row['hit'] ? 'hit' : '' }}">
                                            <span class="n">{{ $row['n'] }}</span><span class="c">{{ $row['text'] }}</span>
                                        </li>
                                    #endforeach
                                </ol>
                            </div>
                        #endif
                    </details>
                #endforeach
                <div class="toggle-row">
                    <label>
                        <input type="checkbox" onclick="document.body.classList.toggle('vendor-on', this.checked)">
                        Show vendor &amp; internal frames
                    </label>
                </div>
            </div>
        </section>

        <section>
            <div class="grid">
                <div>
                    <h2>Request</h2>
                    <table class="kv">
                        <tr><td>Method</td><td class="mono">{{ strtoupper($request['method'] ?? '-') }}</td></tr>
                        <tr><td>URI</td><td class="mono">{{ $request['uri'] ?? '-' }}</td></tr>
                        <tr><td>Route</td><td class="mono">{{ $request['route'] ?? '—' }}</td></tr>
                        <tr><td>Client</td><td class="mono">{{ $request['ip'] ?? '—' }}</td></tr>
                        #if(!empty($request['query']))
                            <tr><td>Query</td><td class="mono">{{ json_encode($request['query']) }}</td></tr>
                        #endif
                        #if(!empty($request['input']))
                            <tr><td>Input</td><td class="mono">{{ json_encode($request['input']) }}</td></tr>
                        #endif
                    </table>
                </div>
                <div>
                    <h2>Environment</h2>
                    <table class="kv">
                        <tr><td>App env</td><td class="mono">{{ $env['name'] }}</td></tr>
                        <tr><td>Debug</td><td class="mono">{{ $env['debug'] ? 'on' : 'off' }}</td></tr>
                        <tr><td>PHP</td><td class="mono">{{ $env['php'] }}</td></tr>
                        <tr><td>OpCache</td><td class="mono">{{ $env['opcache'] ? 'on' : 'off' }}</td></tr>
                        <tr><td>Memory peak</td><td class="mono">{{ $env['mem_peak'] }} MB</td></tr>
                    </table>
                </div>
            </div>
        </section>
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

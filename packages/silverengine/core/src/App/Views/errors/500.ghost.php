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
        html { scroll-behavior: smooth }
        body { background: var(--bg); color: var(--fg);
               font: 14px/1.55 ui-sans-serif, system-ui, -apple-system,
                     "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
               -webkit-font-smoothing: antialiased }
        a { color: var(--link); text-decoration: none }
        a:hover { text-decoration: underline }
        .mono { font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace }

        .wrap { max-width: 76rem; margin: 0 auto; padding: 2rem 1.5rem 4rem }

        /* Top utility bar — quick jump to each section, stays in view */
        .toc { position: sticky; top: 0; z-index: 10;
               background: color-mix(in srgb, var(--bg) 92%, transparent);
               backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
               border-bottom: 1px solid var(--border-soft);
               margin: -2rem -1.5rem 2.5rem; padding: .65rem 1.5rem }
        .toc-inner { max-width: 76rem; margin: 0 auto; display: flex;
                     gap: 1.25rem; align-items: center; font-size: 12px;
                     overflow-x: auto; white-space: nowrap }
        .toc .badge { font: 600 10px/1 ui-monospace, monospace;
                      color: white; background: var(--accent);
                      padding: .3rem .5rem; border-radius: .25rem;
                      letter-spacing: .08em }
        .toc a { color: var(--muted); padding: .1rem 0;
                 border-bottom: 1px solid transparent;
                 transition: color .12s, border-color .12s }
        .toc a:hover { color: var(--fg); border-bottom-color: var(--muted-2);
                       text-decoration: none }
        .toc .spacer { flex: 1 }
        .toc .file-pill { color: var(--muted); font: 11.5px ui-monospace, monospace;
                          background: var(--card); border: 1px solid var(--border);
                          padding: .25rem .5rem; border-radius: .25rem;
                          max-width: 24rem; overflow: hidden;
                          text-overflow: ellipsis }

        /* Header */
        header { display: flex; align-items: flex-start; justify-content: space-between;
                 gap: 2rem; padding-bottom: 1.5rem; margin-bottom: 1.5rem;
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

        /* Sections — generous breathing room, anchorable */
        section { margin: 2.5rem 0; scroll-margin-top: 4rem }
        h2 { font-size: 11px; text-transform: uppercase; letter-spacing: .15em;
             color: var(--muted); margin: 0 0 .85rem; font-weight: 600;
             display: flex; align-items: baseline; justify-content: space-between;
             gap: 1rem }
        h2 .meta { font-weight: 400; letter-spacing: 0; text-transform: none;
                   color: var(--muted-2); font-size: 11.5px; display: inline-flex;
                   align-items: center; gap: .5rem }

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
                           font-size: 12px;
                           /* Stay put when the .c content scrolls horizontally */
                           position: sticky; left: 0;
                           background: var(--code-bg) }
        .src-lines li .c { padding: .05rem .85rem;
                           white-space: pre; color: var(--fg) }
        /* Single horizontal scrollbar for the whole block instead of one per
           line — the parent scrolls, the sticky number stays in place. */
        .src-lines { overflow-x: auto }
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

        /* Previous exception chain — visual guide line down the left edge */
        .chain { position: relative; padding-left: 1.25rem }
        .chain::before {
            content: ''; position: absolute; left: .35rem; top: .6rem; bottom: .6rem;
            width: 1px; background: linear-gradient(to bottom,
                var(--accent) 0%, color-mix(in srgb, var(--accent) 30%, transparent) 100%);
        }
        .prev { padding: .65rem .85rem; border: 1px solid var(--border);
                background: var(--card); border-radius: .5rem; margin-bottom: .5rem;
                font-size: 13px; position: relative }
        .prev::before {
            content: ''; position: absolute; left: -1.2rem; top: 1rem;
            width: .9rem; height: 1px; background: var(--accent); opacity: .6;
        }
        .prev::after {
            content: ''; position: absolute; left: -.55rem; top: .85rem;
            width: .35rem; height: .35rem; border-radius: 50%; background: var(--accent);
        }
        .prev .pclass { font-weight: 600; color: var(--accent) }
        .prev .pmsg { color: var(--fg); margin-top: .1rem }
        .prev .ploc { color: var(--muted); font: 12px ui-monospace, monospace;
                      margin-top: .15rem }

        /* PHP token colours — kept inline so the page renders even with
           the asset build broken. Palette matches the Prism-style we
           use on /docs but is hand-tuned for the zinc background. */
        .src-lines .t-kw   { color: #2563eb; font-weight: 500 }   /* keywords */
        .src-lines .t-id   { color: #0f172a }                     /* identifiers */
        .src-lines .t-var  { color: #c2410c }                     /* $variable */
        .src-lines .t-str  { color: #16a34a }                     /* 'string' */
        .src-lines .t-num  { color: #be185d }                     /* 42, 3.14 */
        .src-lines .t-cm   { color: #71717a; font-style: italic } /* // comment */
        .src-lines .t-doc  { color: #71717a; font-style: italic } /* docblock */
        .src-lines .t-tag  { color: #94a3b8 }                     /* PHP open/close tag */
        .src-lines .t-pun  { color: #475569 }                     /* punctuation */
        .src-lines .t-html { color: #475569 }                     /* inline HTML */
        @media (prefers-color-scheme: dark) {
            .src-lines .t-kw   { color: #93c5fd }
            .src-lines .t-id   { color: #e4e4e7 }
            .src-lines .t-var  { color: #fdba74 }
            .src-lines .t-str  { color: #86efac }
            .src-lines .t-num  { color: #fda4af }
            .src-lines .t-cm   { color: #71717a }
            .src-lines .t-doc  { color: #71717a }
            .src-lines .t-tag  { color: #64748b }
            .src-lines .t-pun  { color: #94a3b8 }
            .src-lines .t-html { color: #94a3b8 }
        }

        /* Solution hints */
        .hints { display: flex; flex-direction: column; gap: .5rem; margin-bottom: 1.25rem }
        .hint { border: 1px solid var(--border); border-left: 3px solid #f59e0b;
                background: var(--card); border-radius: .5rem; padding: .6rem .85rem }
        .hint .htitle { font-weight: 600; font-size: 12.5px; color: #f59e0b;
                        text-transform: uppercase; letter-spacing: .08em; margin-bottom: .25rem }
        .hint .hbody { font-size: 13px; color: var(--fg); line-height: 1.5 }
        .hint .hbody code { font-family: ui-monospace, monospace; font-size: 12px;
                            background: var(--code-bg); padding: .1rem .35rem;
                            border-radius: 3px; border: 1px solid var(--border-soft) }
        .hint .hbody a { color: #f59e0b }

        /* Copy buttons */
        .action-row { display: flex; gap: .4rem; flex-wrap: wrap;
                      margin-top: 1.1rem; align-items: center }
        .copy-btn { font: 500 12px/1 ui-sans-serif, system-ui, sans-serif;
                    padding: .45rem .7rem; border: 1px solid var(--border);
                    border-radius: .35rem; background: var(--card);
                    color: var(--muted); cursor: pointer;
                    transition: all .15s ease-out; flex-shrink: 0;
                    display: inline-flex; align-items: center; gap: .35rem }
        .copy-btn:hover { color: var(--fg); border-color: var(--muted-2);
                          background: var(--bg) }
        .copy-btn.ok { color: #16a34a; border-color: #16a34a;
                       background: rgba(22,163,74,.06) }
        .copy-btn.primary { color: var(--fg); font-weight: 600;
                            background: linear-gradient(180deg,
                              color-mix(in srgb, #8b5cf6 8%, var(--card)),
                              var(--card));
                            border-color: color-mix(in srgb, #8b5cf6 35%, var(--border));
                            box-shadow: 0 1px 2px rgba(139,92,246,.08) }
        .copy-btn.primary:hover { border-color: #8b5cf6;
                                  background: linear-gradient(180deg,
                                    color-mix(in srgb, #8b5cf6 15%, var(--card)),
                                    var(--card));
                                  box-shadow: 0 2px 8px -2px rgba(139,92,246,.25) }
        .copy-btn .ico { font-size: 13px; line-height: 1 }

        /* Full-file disclosure under the source viewer */
        details.full-file { margin-top: .5rem }
        details.full-file > summary { list-style: none; cursor: pointer;
            display: inline-flex; align-items: center; gap: .5rem;
            font: 500 12px ui-sans-serif; color: var(--muted);
            padding: .45rem .7rem; border: 1px solid var(--border);
            border-radius: .4rem; background: var(--card); user-select: none;
            transition: color .12s, border-color .12s }
        details.full-file > summary::-webkit-details-marker { display: none }
        details.full-file > summary::before { content: '⌄'; transition: transform .15s }
        details.full-file[open] > summary::before { transform: rotate(180deg) }
        details.full-file > summary:hover { color: var(--fg); border-color: var(--muted-2) }
        details.full-file > summary .meta { color: var(--muted-2); font-weight: 400 }
        .full-src { max-height: 70vh; overflow: auto; margin-top: .5rem;
                    scroll-behavior: smooth }
        .full-src .src-lines li:target {
            box-shadow: inset 3px 0 0 var(--hit-rail);
        }

        /* Recordings panel */
        .recordings { border: 1px solid var(--border); border-radius: .65rem;
                      background: var(--card); overflow: hidden; font-size: 12.5px }
        .rec-row { display: grid; grid-template-columns: auto 4rem 1fr 4rem 4.5rem;
                   gap: .65rem; align-items: center; padding: .45rem .85rem;
                   border-top: 1px solid var(--border-soft); font-family: ui-monospace, monospace }
        .rec-row:first-child { border-top: 0 }
        .rec-row:hover { background: var(--code-bg) }
        .rec-row a { color: inherit; display: contents }
        .rec-row .when    { color: var(--muted-2); font-size: 11px }
        .rec-row .method  { color: var(--muted); font-weight: 600; font-size: 10.5px }
        .rec-row .path    { color: var(--fg); overflow: hidden; text-overflow: ellipsis;
                            white-space: nowrap }
        .rec-row .status  { font-size: 11px; text-align: right }
        .rec-row .status.s2 { color: #16a34a } .rec-row .status.s3 { color: #2563eb }
        .rec-row .status.s4 { color: #f59e0b } .rec-row .status.s5 { color: #dc2626 }
        .rec-row .ms      { color: var(--muted-2); font-size: 11px; text-align: right }

        /* Active TOC link (driven by the scroll-spy script below) */
        .toc a.active { color: var(--fg); border-bottom-color: var(--accent) }

        /* Mobile / narrow */
        @media (max-width: 720px) {
            .wrap { padding: 1.5rem 1rem 3rem }
            .toc { margin-left: -1rem; margin-right: -1rem; padding: .55rem 1rem }
            .toc-inner { gap: .85rem; font-size: 12.5px }
            .toc .file-pill { display: none }   /* path repeats in the header */

            header { flex-direction: column; gap: 1rem;
                     padding-bottom: 1.25rem; margin-bottom: 1.25rem }
            .meta-pills { max-width: none; justify-content: flex-start }
            h1.err-class { font-size: 1.4rem }
            .action-row { gap: .3rem }
            .copy-btn { padding: .4rem .55rem; font-size: 11.5px }

            /* Stack frames: drop the wide grid in favour of a stack */
            .trace summary { grid-template-columns: auto 1fr; row-gap: .25rem }
            .trace summary .at { grid-column: 1 / -1; text-align: left;
                                 padding-left: 5rem }
        }

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
        <nav class="toc" aria-label="Error page sections">
            <div class="toc-inner">
                <span class="badge" aria-hidden="true">500</span>
                #if(count($solutions))
                    <a href="#hints" data-section="hints">Suggestions</a>
                #endif
                <a href="#source" data-section="source">Source</a>
                <a href="#stack"  data-section="stack">Stack</a>
                <a href="#request" data-section="request">Request</a>
                #if(count($recordings))
                    <a href="#recent" data-section="recent">Recent</a>
                #endif
                <span class="spacer"></span>
                <span class="file-pill" title="{{ $rel_file ?? $file }}:{{ $line }}">
                    {{ $rel_file ?? $file }}:{{ $line }}
                </span>
            </div>
        </nav>

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
                <div class="action-row">
                    <button class="copy-btn primary" data-copy-ai title="Builds an AI-ready prompt with the error, source, stack, and env — paste into ChatGPT or Claude">
                        <span class="ico">✨</span> Copy AI prompt
                    </button>
                    <button class="copy-btn" data-copy="{{ $class }}: {{ $message }}" title="Copy error class + message">
                        copy error
                    </button>
                    <button class="copy-btn" data-copy="{{ $rel_file ?? $file }}:{{ $line }}" title="Copy the file path and line">
                        copy location
                    </button>
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

        <textarea id="ai-prompt-source" hidden aria-hidden="true">{{ $ai_prompt }}</textarea>

        #if(count($solutions))
            <section id="hints">
                <h2>Suggestions</h2>
                <div class="hints">
                    #foreach($solutions as $hint)
                        <div class="hint">
                            <div class="htitle">{{ $hint['title'] }}</div>
                            <div class="hbody">{!! $hint['body'] !!}</div>
                        </div>
                    #endforeach
                </div>
            </section>
        #endif

        #if(count($previous))
            <section>
                <h2>Caused by</h2>
                <div class="chain">
                    #foreach($previous as $p)
                        <div class="prev">
                            <span class="pclass">{{ $p['class'] }}</span>
                            <div class="pmsg">{{ $p['message'] }}</div>
                            <div class="ploc">{{ $p['file'] }}:{{ $p['line'] }}</div>
                        </div>
                    #endforeach
                </div>
            </section>
        #endif

        <section id="source">
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
                            <span class="n" aria-hidden="true">{{ $row['n'] }}</span><span class="c">{!! $row['html'] !!}</span>
                        </li>
                    #endforeach
                </ol>
            </div>

            #if(!empty($full_source['rows']))
                <details class="full-file">
                    <summary>
                        View full file
                        <span class="meta">
                            {{ count($full_source['rows']) }} of {{ $full_source['total'] }} lines
                            #if($full_source['truncated'])
                                · truncated at {{ count($full_source['rows']) }} for safety
                            #endif
                        </span>
                    </summary>
                    <div class="src full-src">
                        <ol class="src-lines">
                            #foreach($full_source['rows'] as $row)
                                <li id="L{{ $row['n'] }}" class="{{ $row['hit'] ? 'hit' : '' }}">
                                    <span class="n" aria-hidden="true">{{ $row['n'] }}</span><span class="c">{!! $row['html'] !!}</span>
                                </li>
                            #endforeach
                        </ol>
                    </div>
                </details>
            #endif
        </section>

        <section id="stack">
            <h2>
                Stack trace
                <span class="meta">
                    {{ count($frames) }} frames
                    <button class="copy-btn" data-copy-trace>copy trace</button>
                </span>
            </h2>
            <input id="show-vendor" type="checkbox">
            <div class="trace">
                #foreach($frames as $f)
                    <details class="kind-{{ $f['kind'] }}" {{ $f['is_first_app'] ? 'open' : '' }}>
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
                                            <span class="n" aria-hidden="true">{{ $row['n'] }}</span><span class="c">{!! $row['html'] !!}</span>
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

        <section id="request">
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

        #if(count($recordings))
            <section id="recent">
                <h2>
                    Recent requests
                    <span class="meta">last {{ count($recordings) }} via /debug recordings</span>
                </h2>
                <div class="recordings">
                    #foreach($recordings as $r)
                        <div class="rec-row" title="Open in /debug">
                            <a href="/debug?tab=recordings#{{ $r['id'] }}" title="Open the recordings tab">
                                <span class="when">{{ $r['at'] }}</span>
                                <span class="method">{{ $r['method'] }}</span>
                                <span class="path">{{ $r['path'] }}</span>
                                <span class="status s{{ (int) ($r['status'] / 100) }}">{{ $r['status'] }}</span>
                                <span class="ms">{{ $r['total_ms'] }} ms</span>
                            </a>
                        </div>
                    #endforeach
                </div>
            </section>
        #endif
    </div>

    <script>
    // Scroll-spy: highlight the TOC link whose section is currently in
    // view. CSS-only :target only handles clicks, not freeform scroll —
    // hence this ~15 lines of IntersectionObserver. Page works fine if
    // the script never runs.
    (function () {
        var links = document.querySelectorAll('.toc a[data-section]');
        if (!links.length || typeof IntersectionObserver !== 'function') return;
        var map = {};
        links.forEach(function (a) { map[a.dataset.section] = a; });
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                var link = map[e.target.id];
                if (!link) return;
                if (e.isIntersecting) {
                    links.forEach(function (a) { a.classList.remove('active'); });
                    link.classList.add('active');
                }
            });
        }, { rootMargin: '-40% 0px -55% 0px', threshold: 0 });
        Object.keys(map).forEach(function (id) {
            var s = document.getElementById(id);
            if (s) io.observe(s);
        });
    })();

    // Minimal, dependency-free clipboard. Page renders fine without it.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.copy-btn');
        if (!btn) return;
        e.preventDefault();
        var payload;
        if (btn.hasAttribute('data-copy-ai')) {
            // Server-built prompt sits in a hidden <textarea>; read its raw
            // value so newlines and braces survive HTML escaping intact.
            var src = document.getElementById('ai-prompt-source');
            payload = src ? src.value : '';
        } else if (btn.hasAttribute('data-copy-trace')) {
            // Build a plain-text stack trace from the rendered frames.
            payload = Array.from(document.querySelectorAll('.trace details')).map(function (d) {
                var where = d.querySelector('.where');
                var at    = d.querySelector('.at');
                return (where ? where.textContent.trim() : '') +
                       (at    ? '   ' + at.textContent.trim() : '');
            }).join('\n');
        } else {
            payload = btn.getAttribute('data-copy') || '';
        }
        var done = function () {
            var orig = btn.innerHTML;
            btn.innerHTML = '<span class="ico">✓</span> copied';
            btn.classList.add('ok');
            setTimeout(function () {
                btn.innerHTML = orig;
                btn.classList.remove('ok');
            }, 1400);
        };
        // Try the modern API, fall back to a hidden textarea + execCommand
        // for environments where the Clipboard API is gated (older Safari,
        // non-HTTPS in some browsers).
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(payload).then(done).catch(legacy);
        } else {
            legacy();
        }
        function legacy() {
            var ta = document.createElement('textarea');
            ta.value = payload;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); done(); }
            catch (_) { /* give up silently */ }
            document.body.removeChild(ta);
        }
    });
    </script>
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

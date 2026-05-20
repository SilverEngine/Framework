<?php
/**
 * @var bool   $debug
 * @var bool   $is_local
 * @var string $message
 * @var string $uri
 * @var string $suggested
 */
$path = strtok($uri ?? '/', '?');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 — Not Found</title>
    <style>
        /* Self-contained: must render without the asset build. */
        :root { color-scheme: light }
        * { box-sizing: border-box }
        html, body { margin: 0; padding: 0; height: 100% }
        body { background:#fff; color:#18181b; font:15px/1.55 ui-sans-serif,system-ui,
               -apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
               -webkit-font-smoothing:antialiased }
        .wrap { max-width:42rem; margin:0 auto; padding:5rem 1.5rem 3rem }
        .eyebrow { font-size:12px; letter-spacing:.12em; text-transform:uppercase; color:#a1a1aa; margin:0 0 1rem }
        h1 { font-size:2.25rem; font-weight:500; letter-spacing:-.01em; margin:0 0 .5rem; line-height:1.1 }
        h1 .muted { color:#a1a1aa }
        p { color:#52525b; margin:0 0 1rem }
        code { font:13px ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
               background:#f4f4f5; border:1px solid #e4e4e7; padding:.15rem .35rem; border-radius:3px }
        hr { border:0; border-top:1px solid #e4e4e7; margin:2.5rem 0 }
        .actions a { font-size:14px; font-weight:500; color:#18181b; text-decoration:none;
                     border-bottom:1px solid #18181b; padding-bottom:1px; margin-right:1.5rem }
        .actions a:hover { color:#71717a; border-color:#d4d4d8 }
        form.scaffold { display:flex; flex-direction:column; gap:.75rem; max-width:32rem }
        form.scaffold label { font-size:12px; color:#71717a; letter-spacing:.04em }
        form.scaffold input { font:14px ui-sans-serif,system-ui,sans-serif; padding:.55rem .75rem;
                              border:1px solid #d4d4d8; border-radius:4px; background:#fff; color:#18181b;
                              width:100%; }
        form.scaffold input:focus { outline:none; border-color:#18181b }
        form.scaffold .row { display:flex; gap:.75rem; flex-wrap:wrap }
        form.scaffold .row > div { flex:1; min-width:14rem }
        form.scaffold button { font:14px ui-sans-serif,system-ui,sans-serif; font-weight:500;
                               padding:.55rem 1rem; background:#18181b; color:#fff; border:0;
                               border-radius:4px; cursor:pointer; align-self:flex-start }
        form.scaffold button:hover { background:#3f3f46 }
        .hint { font-size:12.5px; color:#a1a1aa; margin-top:.25rem }
        .footnote { font-size:12px; color:#a1a1aa; margin-top:2.5rem }
    </style>
</head>
<body>
<div class="wrap">
    <p class="eyebrow">404</p>
    <h1>Page not found<?php if (!empty($message) && !empty($debug)): ?> <span class="muted">— <?= htmlspecialchars($message, ENT_QUOTES) ?></span><?php endif; ?></h1>
    <p>Nothing is wired to <code><?= htmlspecialchars($path, ENT_QUOTES) ?></code>.</p>

    <div class="actions">
        <a href="/">Back home</a>
        <?php if (!empty($debug)): ?><a href="/debug">Debug panel</a><?php endif; ?>
    </div>

    <?php if (!empty($is_local) && !empty($debug)): ?>
        <hr>
        <p class="eyebrow">Scaffold</p>
        <p>Generate a Wisp page wired to this URL — controller, Vue component, and the route line.</p>
        <form class="scaffold" method="POST" action="/__silver/scaffold">
            <div class="row">
                <div>
                    <label for="scaffold-url">URL</label>
                    <input id="scaffold-url" name="url" value="<?= htmlspecialchars($path, ENT_QUOTES) ?>" required>
                </div>
                <div>
                    <label for="scaffold-name">Name</label>
                    <input id="scaffold-name" name="name" value="<?= htmlspecialchars($suggested ?? 'Page', ENT_QUOTES) ?>" required pattern="[A-Za-z][A-Za-z0-9]*">
                    <div class="hint">PascalCase. No spaces.</div>
                </div>
            </div>
            <button type="submit">Create page</button>
            <p class="hint">Writes <code>app/Controllers/&lt;Name&gt;Controller.php</code>, <code>app/Resources/js/Pages/&lt;Name&gt;.vue</code>, and appends to <code>app/Routes/Web.php</code>.</p>
        </form>
        <p class="footnote">This panel is only shown when <code>APP_ENV=local</code> &amp; <code>APP_DEBUG=true</code>.</p>
    <?php endif; ?>
</div>
</body>
</html>

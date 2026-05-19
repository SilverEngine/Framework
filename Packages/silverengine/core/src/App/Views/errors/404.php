<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 — Not Found</title>
    <style>
        /* Self-contained fallback: this view is used when the app has no
           App/Views/errors/404 and must render without the build pipeline. */
        :root { color-scheme: dark; }
        body { margin:0; min-height:100vh; display:flex; flex-direction:column;
               align-items:center; justify-content:center; gap:.75rem;
               background:#020617; color:#e2e8f0; text-align:center;
               font:16px/1.5 ui-sans-serif,system-ui,sans-serif; }
        .code { font-size:5rem; font-weight:900; color:#334155; margin:0; }
        h1 { font-size:1.4rem; font-weight:600; margin:0; }
        p { color:#94a3b8; margin:0; }
        a { margin-top:.5rem; padding:.5rem 1.25rem; border-radius:.5rem;
            background:#4f46e5; color:#fff; text-decoration:none; }
        a:hover { background:#6366f1; }
    </style>
</head>
<body>
    <p class="code">404</p>
    <h1>Page not found</h1>
    <p>The requested page could not be found.</p>
    <a href="/">Back home</a>
</body>
</html>

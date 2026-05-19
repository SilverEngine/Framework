<?php

declare(strict_types=1);

namespace Silver\ErrorHandler;

/**
 * First-party error reporter — drop-in replacement for Ouch\Reporter.
 *
 * Surface kept identical to the framework's existing call site:
 *   $r = new Reporter();
 *   $r->on();
 */
final class Reporter
{
    private bool $registered = false;

    public function on(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        error_reporting(E_ALL);

        set_error_handler($this->handleError(...));
        set_exception_handler($this->handleException(...));
        register_shutdown_function($this->handleShutdown(...));
    }

    public function off(): void
    {
        if (!$this->registered) {
            return;
        }
        $this->registered = false;
        restore_error_handler();
        restore_exception_handler();
    }

    /**
     * Severities that should halt execution. Everything else
     * (warnings, notices, deprecations, strict) is logged but
     * must not turn into a fatal — essential for a legacy codebase
     * still being modernized.
     */
    private const FATAL = E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING
        | E_COMPILE_ERROR | E_COMPILE_WARNING | E_USER_ERROR | E_RECOVERABLE_ERROR;

    /**
     * Fatal-class errors become ErrorException so they flow through one
     * path. Non-fatal errors are logged and swallowed (no output, no halt).
     * Respects the @-suppression operator and error_reporting().
     */
    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        if (($severity & self::FATAL) !== 0) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }

        error_log(sprintf('[%d] %s in %s:%d', $severity, $message, $file, $line));

        return true;
    }

    public function handleException(\Throwable $e): void
    {
        $displayErrors = filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        if ($displayErrors) {
            echo $this->renderDebugPage($e);
            return;
        }

        error_log(sprintf(
            '%s: %s in %s:%d',
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
        echo $this->renderMinimalPage();
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }
        if (!in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        $this->handleException(new \ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line']
        ));
    }

    private function renderDebugPage(\Throwable $e): string
    {
        $title = htmlspecialchars($e::class, ENT_QUOTES);
        $msg   = htmlspecialchars($e->getMessage(), ENT_QUOTES);
        $loc   = htmlspecialchars($e->getFile() . ':' . $e->getLine(), ENT_QUOTES);
        $trace = htmlspecialchars($e->getTraceAsString(), ENT_QUOTES);
        $snippet = $this->codeSnippet($e->getFile(), $e->getLine());

        // Self-contained by design: this page renders when the app (and
        // possibly the asset build) is broken, so it must not depend on
        // Vite/Tailwind. Styling mirrors the Tailwind slate/rose palette.
        return <<<HTML
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>{$title}</title>
<style>
 :root{color-scheme:dark}
 *{box-sizing:border-box}
 body{font:14px/1.6 ui-sans-serif,system-ui,sans-serif;background:#020617;color:#e2e8f0;margin:0;padding:2.5rem}
 .wrap{max-width:64rem;margin:0 auto}
 .badge{display:inline-block;font:600 11px/1 ui-sans-serif;letter-spacing:.15em;text-transform:uppercase;color:#fb7185;background:rgba(244,63,94,.12);border:1px solid rgba(244,63,94,.35);padding:.4rem .6rem;border-radius:.4rem;margin-bottom:1rem}
 h1{color:#fda4af;font-size:1.35rem;margin:0 0 .35rem}
 .msg{color:#f1f5f9;margin:0 0 .25rem}
 .loc{color:#7dd3fc;font:13px ui-monospace,Menlo,Consolas,monospace;margin-bottom:1.75rem}
 h2{font-size:.8rem;text-transform:uppercase;letter-spacing:.12em;color:#a78bfa;margin:1.5rem 0 .5rem}
 pre{font:13px/1.6 ui-monospace,Menlo,Consolas,monospace;background:#0f172a;border:1px solid #1e293b;padding:1rem;border-radius:.6rem;overflow:auto;margin:0}
 .snip .cur{background:rgba(244,63,94,.18);display:block;border-radius:2px}
</style></head><body>
<div class="wrap">
 <span class="badge">Unhandled exception</span>
 <h1>{$title}</h1>
 <p class="msg">{$msg}</p>
 <div class="loc">{$loc}</div>
 <h2>Source</h2>
 <pre class="snip">{$snippet}</pre>
 <h2>Stack trace</h2>
 <pre>{$trace}</pre>
</div>
</body></html>
HTML;
    }

    private function renderMinimalPage(): string
    {
        return <<<HTML
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>500 — Server Error</title>
<style>
 :root{color-scheme:dark}
 body{margin:0;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.6rem;background:#020617;color:#e2e8f0;font:16px/1.5 ui-sans-serif,system-ui,sans-serif;text-align:center}
 p.code{font-size:5rem;font-weight:900;color:#3f3f46;margin:0}
 h1{font-size:1.3rem;font-weight:600;margin:0}
 span{color:#94a3b8}
</style></head><body>
<p class="code">500</p><h1>Internal Server Error</h1>
<span>Something went wrong on our end.</span>
</body></html>
HTML;
    }

    private function codeSnippet(string $file, int $line, int $pad = 6): string
    {
        if (!is_file($file) || !is_readable($file)) {
            return '(source unavailable)';
        }
        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return '(source unavailable)';
        }
        $start = max(0, $line - 1 - $pad);
        $end   = min(count($lines) - 1, $line - 1 + $pad);
        $out   = '';
        for ($i = $start; $i <= $end; $i++) {
            $n   = str_pad((string) ($i + 1), 5, ' ', STR_PAD_LEFT);
            $row = htmlspecialchars($n . ' | ' . $lines[$i], ENT_QUOTES);
            $out .= ($i + 1 === $line) ? "<span class=\"cur\">{$row}</span>" : $row . "\n";
        }
        return $out;
    }
}

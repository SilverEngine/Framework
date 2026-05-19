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
     * Convert PHP errors to ErrorException so they flow through one path.
     * Respect the error-suppression operator and error_reporting().
     */
    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new \ErrorException($message, 0, $severity, $file, $line);
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

        return <<<HTML
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>{$title}</title>
<style>
 body{font:14px/1.5 ui-monospace,Menlo,Consolas,monospace;background:#1d1f21;color:#c5c8c6;margin:0;padding:2rem}
 h1{color:#cc6666;font-size:1.2rem;margin:0 0 .25rem}
 .loc{color:#81a2be;margin-bottom:1.5rem}
 pre{background:#282a2e;padding:1rem;border-radius:6px;overflow:auto}
 .snip .cur{background:#3b1f1f;display:block}
</style></head><body>
<h1>{$title}</h1>
<div>{$msg}</div>
<div class="loc">{$loc}</div>
<h2 style="font-size:1rem;color:#b294bb">Source</h2>
<pre class="snip">{$snippet}</pre>
<h2 style="font-size:1rem;color:#b294bb">Stack trace</h2>
<pre>{$trace}</pre>
</body></html>
HTML;
    }

    private function renderMinimalPage(): string
    {
        return "<!doctype html><html><head><title>500</title></head>"
            . "<body><h1>500 — Internal Server Error</h1></body></html>";
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

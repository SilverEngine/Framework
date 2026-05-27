<?php
declare(strict_types=1);

namespace Silver\Core;

use Silver\Support\Facades\Request;
use Silver\Exception\NotFoundException;
use Silver\Exception\ErrorException;
use Silver\Exception\Exception;
use Silver\Http\View;

/**
 * Renders error pages and JSON error bodies, and exposes a filter the
 * View layer toggles when rendering templates. Resolved as a singleton
 * through the container so the filter setting is shared across the
 * request.
 */
final class ErrorHandler
{
    private int $filter = E_ALL;

    public function setFilter(int $filter): void
    {
        $this->filter = $filter;
    }

    public function getFilter(): int
    {
        return $this->filter;
    }

    public function withFilter(int $filter, callable $cb): mixed
    {
        $old = $this->getFilter();
        $this->setFilter($filter);
        $rv = $cb();
        $this->setFilter($old);
        return $rv;
    }

    public function handle_error(int $code, string $message, string $file, int $line): void
    {
        $this->resetCWD();
        if ($code & $this->filter) {
            $ex = new ErrorException($message, $code);
            $ex->setFile($file);
            $ex->setLine($line);
            throw $ex;
        }
    }

    public function handle_fatal(): void
    {
        $this->resetCWD();
        $fatal = error_get_last();
        if ($fatal === null) {
            return;
        }
        $fatalMask = E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING
            | E_COMPILE_ERROR | E_COMPILE_WARNING | E_USER_ERROR;
        if (($fatal['type'] & $fatalMask) === 0) {
            return;
        }
        $ex = new Exception($fatal['message']);
        $ex->setFile($fatal['file']);
        $ex->setLine($fatal['line']);
        $this->handle_ex($ex);
    }

    public function handle_ex(\Throwable $ex): void
    {
        $this->resetCWD();
        if ($ex instanceof Exception) {
            $this->render($ex, true);
        } else {
            $wrapped = new Exception($ex->getMessage(), (int) $ex->getCode());
            $wrapped->setFile($ex->getFile());
            $wrapped->setLine($ex->getLine());
            $this->render($wrapped, true);
        }
    }

    /**
     * Lines around a file:line position. Each row carries both the raw
     * line text and a syntax-highlighted HTML rendering of it (for PHP
     * sources). The view consumes `html` via `{!! !!}` for the hit line
     * and surrounding context.
     *
     * @return list<array{n:int,text:string,html:string,hit:bool}>
     */
    private function codeAroundLines(string $file, int $line, int $pad = 10): array
    {
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }
        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }
        $start = max(0, $line - 1 - $pad);
        $end   = min(count($lines) - 1, $line - 1 + $pad);
        return $this->buildSourceRows($lines, $start, $end, $line, $file);
    }

    /**
     * Full file (capped at MAX_FULL_LINES) as the same row shape as
     * {@see codeAroundLines()} — used by the "view full file" disclosure
     * in the error page. Large files (vendor blobs, generated classes)
     * are truncated so the page stays renderable.
     *
     * @return array{rows:list<array<string,mixed>>,total:int,truncated:bool}
     */
    private const MAX_FULL_LINES = 1000;

    private function codeFullFile(string $file, int $line): array
    {
        if (!is_file($file) || !is_readable($file)) {
            return ['rows' => [], 'total' => 0, 'truncated' => false];
        }
        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return ['rows' => [], 'total' => 0, 'truncated' => false];
        }
        $total = count($lines);
        $end = min($total - 1, self::MAX_FULL_LINES - 1);
        return [
            'rows'      => $this->buildSourceRows($lines, 0, $end, $line, $file),
            'total'     => $total,
            'truncated' => $total > self::MAX_FULL_LINES,
        ];
    }

    /**
     * Shared builder behind codeAroundLines + codeFullFile. Tokenises
     * the source once and emits per-line rows for the requested range.
     *
     * @param  list<string> $lines
     * @return list<array{n:int,text:string,html:string,hit:bool}>
     */
    private function buildSourceRows(array $lines, int $start, int $end, int $hitLine, string $file): array
    {
        $isPhp = str_ends_with(strtolower($file), '.php');
        $highlightedLines = $isPhp ? $this->highlightPhpByLine($lines) : null;

        $out = [];
        for ($i = $start; $i <= $end; $i++) {
            $raw = $lines[$i] ?? '';
            $out[] = [
                'n'    => $i + 1,
                'text' => $raw,
                'html' => $highlightedLines[$i] ?? htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE),
                'hit'  => ($i + 1) === $hitLine,
            ];
        }
        return $out;
    }

    /**
     * Tokenise a PHP source file (line array) and return per-line HTML
     * with tokens wrapped in <span class="t-XYZ"> spans. Uses native
     * `token_get_all` — no external dependency, opcache-friendly.
     *
     * @param  list<string> $lines source split by lines (without \n)
     * @return list<string> per-line HTML
     */
    private function highlightPhpByLine(array $lines): array
    {
        // Reconstruct with explicit \n so token offsets line up.
        $src = implode("\n", $lines);

        try {
            $tokens = @token_get_all($src);
        } catch (\Throwable) {
            // Tokenisation can throw on broken PHP — fall back to
            // plain-text rendering rather than masking the real error.
            return array_map(
                static fn (string $l): string => htmlspecialchars($l, ENT_QUOTES | ENT_SUBSTITUTE),
                $lines,
            );
        }

        $html = '';
        foreach ($tokens as $tok) {
            if (is_array($tok)) {
                [$id, $text] = $tok;
                $cls = self::tokenClass($id, $text);
                $esc = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE);
                $html .= $cls !== ''
                    ? '<span class="t-' . $cls . '">' . $esc . '</span>'
                    : $esc;
            } else {
                // Punctuation: ( ) { } ; , . etc.
                $html .= '<span class="t-pun">' . htmlspecialchars($tok, ENT_QUOTES) . '</span>';
            }
        }

        // Split the reconstructed highlighted source back to lines.
        return explode("\n", $html);
    }

    /** Map a PHP token id to a short CSS class suffix (used as `t-XYZ`). */
    private static function tokenClass(int $id, string $text): string
    {
        // Keywords + structural — sorted by frequency.
        static $keyword = null;
        if ($keyword === null) {
            $keyword = array_flip([
                T_FUNCTION, T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_NAMESPACE,
                T_USE, T_NEW, T_RETURN, T_IF, T_ELSE, T_ELSEIF, T_FOR, T_FOREACH,
                T_WHILE, T_DO, T_SWITCH, T_CASE, T_DEFAULT, T_BREAK, T_CONTINUE,
                T_TRY, T_CATCH, T_FINALLY, T_THROW, T_PUBLIC, T_PRIVATE, T_PROTECTED,
                T_STATIC, T_ABSTRACT, T_FINAL, T_READONLY, T_CONST, T_ECHO, T_PRINT,
                T_INSTANCEOF, T_INSTEADOF, T_AS, T_EXTENDS, T_IMPLEMENTS, T_FN,
                T_MATCH, T_YIELD, T_GLOBAL, T_REQUIRE, T_REQUIRE_ONCE,
                T_INCLUDE, T_INCLUDE_ONCE, T_DECLARE, T_ENDDECLARE,
            ]);
        }
        if (isset($keyword[$id]))                          return 'kw';
        if ($id === T_VARIABLE)                            return 'var';
        if ($id === T_CONSTANT_ENCAPSED_STRING)            return 'str';
        if ($id === T_ENCAPSED_AND_WHITESPACE)             return 'str';
        if ($id === T_INLINE_HTML)                         return 'html';
        if ($id === T_COMMENT)                             return 'cm';
        if ($id === T_DOC_COMMENT)                         return 'doc';
        if ($id === T_LNUMBER || $id === T_DNUMBER)        return 'num';
        if ($id === T_OPEN_TAG || $id === T_CLOSE_TAG)     return 'tag';
        if ($id === T_STRING)                              return 'id';
        if ($id === T_NAME_FULLY_QUALIFIED
            || $id === T_NAME_QUALIFIED
            || $id === T_NAME_RELATIVE)                    return 'id';
        if ($id === T_WHITESPACE)                          return '';
        return '';
    }

    /**
     * Pattern-matched hint for the user. Returns a list of [title, body]
     * suggestions for common exception classes / message patterns —
     * 'Class not found' suggests dump-autoload, ParseError points at the
     * line, PDOException prompts a DB-config check. Empty list when we
     * don't recognise the shape.
     *
     * @return list<array{title:string,body:string}>
     */
    private function suggestSolutions(\Throwable $e): array
    {
        $class   = $e::class;
        $message = $e->getMessage();
        $hints   = [];

        if ($e instanceof \ParseError) {
            $hints[] = [
                'title' => 'PHP parse error',
                'body'  => 'The interpreter could not parse '
                    . basename($e->getFile()) . '. The arrow below points at the offending token; '
                    . 'check the line above for an unbalanced bracket, missing semicolon, or stray character.',
            ];
        }

        if ($e instanceof \TypeError) {
            $hints[] = [
                'title' => 'Type mismatch',
                'body'  => 'A value reached a typed declaration that disagreed with it. '
                    . 'Look at the call site immediately below the throw line — the argument '
                    . 'or return value there is what disagrees with the type hint.',
            ];
        }

        if ($e instanceof \Error && str_contains($message, 'Class ') && str_contains($message, ' not found')) {
            $hints[] = [
                'title' => 'Class autoload failure',
                'body'  => 'PHP could not autoload that class. Common causes: '
                    . 'the file is namespaced wrong, the case-sensitive filename does not match the class, '
                    . 'or the composer classmap is stale — try <code>composer dump-autoload -o</code>.',
            ];
        }

        if ($e instanceof \PDOException || str_contains($message, 'SQLSTATE')) {
            $hints[] = [
                'title' => 'Database error',
                'body'  => 'A PDO statement failed. Check <code>.env</code> for the right DB_* values, '
                    . 'verify the connection at <a href="/heartbeat">/heartbeat</a>, and confirm the '
                    . 'migration that owns this table has been run.',
            ];
        }

        if ($e instanceof \Silver\Exception\NotFoundException
            || str_contains($message, 'Route for')
            || str_contains($message, 'Controller not found')) {
            $hints[] = [
                'title' => 'Route or controller not found',
                'body'  => 'No route matches the URL, or the controller class could not be resolved. '
                    . 'Check <code>app/Routes/Web.php</code>, then run <code>php silver optimize:clear</code> '
                    . 'if the route was added recently.',
            ];
        }

        if (str_contains($message, 'undefined function') || str_contains($message, 'Call to undefined function')) {
            $hints[] = [
                'title' => 'Undefined function',
                'body'  => 'PHP could not resolve a global function call. Check the namespace, '
                    . 'the spelling, and whether the function lives in a file that is autoloaded.',
            ];
        }

        if (str_contains($message, 'unable to open database file')) {
            $hints[] = [
                'title' => 'SQLite path mismatch',
                'body'  => 'SQLite could not open the file at <code>DB_DATABASE</code>. The directory '
                    . 'may not exist, or the case in the path does not match the actual filesystem '
                    . '(Linux is case-sensitive).',
            ];
        }

        return $hints;
    }

    /**
     * Last N recordings from `storage/debug/recordings/` for the
     * 'recent requests' panel — file paths sorted by mtime (newest
     * first). Each entry: id, at, method, path, status, total_ms.
     *
     * @return list<array<string,mixed>>
     */
    private function recentRecordings(int $limit = 5): array
    {
        $dir = (defined('ROOT') ? \ROOT : '') . 'storage/debug/recordings';
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir . '/*.json') ?: [];
        if ($files === []) {
            return [];
        }
        // Filenames start with epoch_ms — sortable lexicographically.
        rsort($files);
        $out = [];
        foreach (array_slice($files, 0, $limit) as $path) {
            $raw = @file_get_contents($path);
            if ($raw === false) continue;
            $data = json_decode($raw, true);
            if (!is_array($data)) continue;
            $out[] = [
                'id'       => (string) ($data['id'] ?? ''),
                'at'       => (string) ($data['at'] ?? ''),
                'method'   => (string) ($data['method'] ?? '—'),
                'path'     => (string) ($data['path'] ?? '—'),
                'status'   => (int)    ($data['status'] ?? 0),
                'total_ms' => round((float) ($data['total_ms'] ?? 0), 2),
            ];
        }
        return $out;
    }

    /** Legacy plain-text accessor — kept for any external caller. */
    private function codeAround(Exception $ex, int $around = 3): string
    {
        $file = $ex->getFile();
        $line = $ex->getLine();

        if (file_exists($file)) {
            return implode("\n", array_slice(file($file), $line - $around, $around * 2 + 1));
        }

        return "Not a file: '" . print_r($file, true) . "'";
    }

    /**
     * Classify a frame's source file by directory:
     *
     *   app       — application code (`app/`)
     *   framework — Silver core (`packages/silverengine/`)
     *   vendor    — Composer / external (`vendor/`)
     *   internal  — PHP internal call ([internal] or unknown)
     */
    private function frameKind(string $file): string
    {
        if ($file === '' || $file === '[internal]') {
            return 'internal';
        }
        $root = defined('ROOT') ? \ROOT : '';
        $rel  = $root !== '' && str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;

        return match (true) {
            str_starts_with($rel, 'vendor/')                  => 'vendor',
            str_starts_with($rel, 'packages/silverengine/')   => 'framework',
            str_starts_with($rel, 'app/'), str_starts_with($rel, 'config/'),
            str_starts_with($rel, 'public/'), str_starts_with($rel, 'database/'),
            str_starts_with($rel, 'tests/')                   => 'app',
            default                                           => 'app',
        };
    }

    /**
     * `phpstorm://`, `vscode://`, or null. Driven by IDE_PROTOCOL env var
     * so users can pick their editor; defaults to phpstorm.
     */
    private function ideLink(string $file, int|string $line): ?string
    {
        if ($file === '' || $file === '[internal]' || !is_file($file)) {
            return null;
        }
        $proto = (string) ($_ENV['IDE_PROTOCOL'] ?? $_SERVER['IDE_PROTOCOL'] ?? 'phpstorm');
        return match ($proto) {
            'vscode'   => 'vscode://file' . $file . ':' . (int) $line,
            'phpstorm' => 'phpstorm://open?url=file://' . rawurlencode($file) . '&line=' . (int) $line,
            default    => null,
        };
    }

    /**
     * Flatten a throwable's trace into display frames with metadata
     * the new error view consumes:
     *
     *   where    Class::method() / function()
     *   file     absolute path or '[internal]'
     *   rel      project-relative path for compact display
     *   line     line number (or '' when internal)
     *   kind     app | framework | vendor | internal
     *   ide      IDE deep link or null
     *   snippet  6-line code context (3 before, 3 after) or []
     *
     * @return list<array<string,mixed>>
     */
    private function normalizeFrames(\Throwable $e): array
    {
        $root = defined('ROOT') ? \ROOT : '';
        $frames = [];
        $firstAppMarked = false;
        foreach ($e->getTrace() as $f) {
            $where = ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '') . '()';
            $file  = $f['file'] ?? '[internal]';
            $line  = $f['line'] ?? '';
            $rel   = $root !== '' && is_string($file) && str_starts_with($file, $root)
                ? substr($file, strlen($root)) : $file;
            $kind  = $this->frameKind($file);

            // The first app-kind frame gets auto-opened by the view —
            // it's almost always what the dev wants to see first.
            $isFirstApp = false;
            if (!$firstAppMarked && $kind === 'app') {
                $isFirstApp     = true;
                $firstAppMarked = true;
            }

            $frames[] = [
                'where'         => $where === '()' ? '{main}' : $where,
                'file'          => $file,
                'rel'           => $rel,
                'line'          => $line,
                'kind'          => $kind,
                'is_first_app'  => $isFirstApp,
                'ide'           => is_string($file) && $line !== ''
                    ? $this->ideLink($file, (int) $line)
                    : null,
                'snippet'       => is_string($file) && is_int($line) && $line > 0
                    ? $this->codeAroundLines($file, $line, 3)
                    : [],
            ];
        }
        return $frames;
    }

    /**
     * Build a single plain-text block summarising the error in a form
     * an LLM (ChatGPT / Claude / etc.) can answer questions about. The
     * "Copy AI prompt" button in the error page copies this verbatim so
     * the user just has to paste and ask.
     *
     * Includes the exception class + message, the surrounding source
     * lines with the hit row arrow-pointed, the app-side stack frames
     * (vendor + internal noise stripped for signal), and the env block.
     */
    private function aiPrompt(\Throwable $e, string $relFile): string
    {
        $lines = [];
        $lines[] = "Help me debug this PHP error. What's wrong and how do I fix it?";
        $lines[] = '';
        $lines[] = '## Error';
        $lines[] = $e::class . ': ' . $e->getMessage();
        $lines[] = 'at ' . $relFile . ':' . $e->getLine();

        // Source snippet around the hit — keep it tight (5 either side)
        // so the prompt stays compact.
        $snippet = $this->codeAroundLines($e->getFile(), (int) $e->getLine(), 5);
        if ($snippet !== []) {
            $lines[] = '';
            $lines[] = '## Source';
            $lines[] = '```php';
            foreach ($snippet as $row) {
                $arrow  = $row['hit'] ? '> ' : '  ';
                $lines[] = $arrow . str_pad((string) $row['n'], 4, ' ', STR_PAD_LEFT)
                    . ' | ' . $row['text'];
            }
            $lines[] = '```';
        }

        // App + framework frames only — the LLM doesn't need 12 frames
        // of Composer\ClassLoader noise.
        $appFrames = array_values(array_filter(
            $this->normalizeFrames($e),
            static fn (array $f): bool =>
                ($f['kind'] ?? '') === 'app' || ($f['kind'] ?? '') === 'framework',
        ));
        if ($appFrames !== []) {
            $lines[] = '';
            $lines[] = '## Stack (app + framework only)';
            foreach (array_slice($appFrames, 0, 12) as $f) {
                $lines[] = sprintf(
                    '- %s   at %s%s',
                    $f['where'],
                    $f['rel'],
                    $f['line'] !== '' ? ':' . $f['line'] : '',
                );
            }
        }

        // Request context (what URL/route was hit, with secrets redacted).
        $req = $this->requestContext();
        $lines[] = '';
        $lines[] = '## Request';
        $lines[] = sprintf(
            '%s %s   route=%s',
            strtoupper((string) ($req['method'] ?? '-')),
            (string) ($req['uri'] ?? '-'),
            (string) ($req['route'] ?? '—'),
        );

        $lines[] = '';
        $lines[] = '## Environment';
        $lines[] = 'PHP ' . PHP_VERSION
            . ', env=' . \Silver\Core\Env::name()
            . ', debug=' . ((bool) \Silver\Core\Env::get('debug') ? 'on' : 'off');
        $lines[] = 'Live framework status available at /heartbeat (JSON: GET /heartbeat?view=json).';

        return implode("\n", $lines);
    }

    /**
     * Walk the previous-exception chain into a flat list for display.
     * The current exception is excluded — only ancestors.
     *
     * @return list<array{class:string,message:string,file:string,line:int}>
     */
    private function previousChain(\Throwable $e): array
    {
        $chain = [];
        $prev = $e->getPrevious();
        while ($prev !== null) {
            $chain[] = [
                'class'   => $prev::class,
                'message' => $prev->getMessage(),
                'file'    => $prev->getFile(),
                'line'    => $prev->getLine(),
            ];
            $prev = $prev->getPrevious();
        }
        return $chain;
    }

    /**
     * Best-effort request context for the debug page. Never throws —
     * the error page must render even if the request is unavailable.
     *
     * @return array<string,mixed>
     */
    private function requestContext(): array
    {
        try {
            $route = Request::route();

            return [
                'method' => Request::method(),
                'uri'    => Request::getUri() ?? ($_SERVER['REQUEST_URI'] ?? ''),
                'route'  => $route?->name() ?? '—',
                'query'  => $this->redactSensitive($_GET),
                'input'  => $this->redactSensitive(
                    Request::method() === 'get' ? [] : Request::all(),
                ),
                'ip'     => $_SERVER['REMOTE_ADDR'] ?? '—',
            ];
        } catch (\Throwable) {
            return [
                'method' => $_SERVER['REQUEST_METHOD'] ?? '—',
                'uri'    => $_SERVER['REQUEST_URI'] ?? '—',
                'route'  => '—',
                'query'  => $this->redactSensitive($_GET),
                'input'  => [],
                'ip'     => $_SERVER['REMOTE_ADDR'] ?? '—',
            ];
        }
    }

    /**
     * Walk an array and replace values for likely-secret keys with a
     * placeholder so the error page does not leak credentials when a
     * login form (or similar) throws. Recursive; preserves structure.
     *
     * @param  array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function redactSensitive(array $data): array
    {
        static $pattern = '/(?:password|passwd|pwd|secret|token|api[-_]?key|authorization|bearer|session|cookie|csrf|otp|pin)/i';

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->redactSensitive($value);
                continue;
            }
            if (is_string($key) && preg_match($pattern, $key)) {
                $data[$key] = '[REDACTED]';
            }
        }
        return $data;
    }

    /**
     * Consistent API error envelope (same shape for 404, 500, anything).
     * Always: status + message. Debug only: exception class, file, line
     * and the normalized stack frames (same frames as the HTML page).
     * Pure — no output/exit — so it is unit-testable.
     *
     * @return array<string,mixed>
     */
    public function apiErrorBody(Exception $e, int $status): array
    {
        $body = [
            'status'  => $status,
            'message' => $e->getMessage() ?: 'Error',
        ];

        if ($this->isDebug()) {
            $orig = $e->getPrevious() ?? $e;
            $body['exception'] = $orig::class;
            $body['file']      = $e->getFile();
            $body['line']      = $e->getLine();
            $body['trace']     = $this->normalizeFrames($orig);
        }

        return $body;
    }

    public function render(Exception $e, bool $finalize = false): mixed
    {
        $view = null;

        if (Request::segment(1) === 'api' || (Request::segment(1) === 'public' && Request::segment(2) === 'api')) {
            try {
                $status = $e instanceof NotFoundException
                    ? ($e->getCode() ?: 404)
                    : 500;

                if (!headers_sent()) {
                    http_response_code($status);
                    header('Content-Type: application/json; charset=utf-8');
                }

                $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    | ($this->isDebug() ? JSON_PRETTY_PRINT : 0);

                echo json_encode(['error' => $this->apiErrorBody($e, $status)], $flags);
                exit();
            } catch (\Throwable $e2) {
                $this->finalize("Fatal error: " . $e2->getMessage());
            }
        } else {
            try {
                if ($e instanceof NotFoundException) {
                    $view = View::make('errors.404')
                        ->with('message', $e->getMessage())
                        ->with('debug', $this->isDebug())
                        ->with('uri', $_SERVER['REQUEST_URI'] ?? '/')
                        ->with('is_local', \Silver\Core\Env::name() === 'local')
                        ->with('suggested', \Silver\Support\Scaffolder::suggestName($_SERVER['REQUEST_URI'] ?? ''));
                } else {
                    $orig = $e->getPrevious() ?? $e;
                    $view = View::make('errors.500')
                        ->with('message', $e->getMessage())
                        ->with('class', $orig::class)
                        ->with('file', $e->getFile())
                        ->with('rel_file', defined('ROOT') && str_starts_with($e->getFile(), \ROOT)
                            ? substr($e->getFile(), strlen(\ROOT)) : $e->getFile())
                        ->with('line', $e->getLine())
                        ->with('source', $this->codeAroundLines($e->getFile(), (int) $e->getLine(), 10))
                        ->with('full_source', $this->codeFullFile($e->getFile(), (int) $e->getLine()))
                        ->with('source_ide', $this->ideLink($e->getFile(), (int) $e->getLine()))
                        ->with('frames', $this->normalizeFrames($orig))
                        ->with('previous', $this->previousChain($orig))
                        ->with('request', $this->requestContext())
                        ->with('env', [
                            'php'      => PHP_VERSION,
                            'name'     => \Silver\Core\Env::name(),
                            'debug'    => (bool) \Silver\Core\Env::get('debug'),
                            'opcache'  => function_exists('opcache_get_status')
                                && @opcache_get_status(false)['opcache_enabled'] === true,
                            'mem_peak' => round(memory_get_peak_usage(true) / 1048576, 2),
                        ])
                        ->with('solutions', $this->suggestSolutions($orig))
                        ->with('recordings', $this->recentRecordings(5))
                        ->with('ai_prompt', $this->aiPrompt(
                            $orig,
                            defined('ROOT') && str_starts_with($e->getFile(), \ROOT)
                                ? substr($e->getFile(), strlen(\ROOT))
                                : $e->getFile(),
                        ))
                        ->with('debug', $this->isDebug());
                }

                if ($finalize) {
                    $this->finalize($view);
                } else {
                    return $view;
                }
            } catch (\Throwable $e2) {
                $this->finalize("Fatal error: " . $e2->getMessage());
            }
        }

        return null;
    }

    private function finalize(mixed $content): never
    {
        http_response_code(500);

        if ($content instanceof View) {
            echo $content->render();
        } else {
            echo $content;
        }
        exit;
    }

    private function isDebug(): bool
    {
        return (bool) Env::get('debug', false);
    }

    private function resetCWD(): void
    {
        if (defined('ROOT')) {
            chdir(ROOT);
        }
    }
}

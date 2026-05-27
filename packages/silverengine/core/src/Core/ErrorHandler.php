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
     * Lines around a file:line position. Returns a list of
     * [n, text, isHit] tuples so the view can lay them out as a table
     * with the error line highlighted instead of receiving a flat blob.
     *
     * @return list<array{n:int,text:string,hit:bool}>
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
        $out   = [];
        for ($i = $start; $i <= $end; $i++) {
            $out[] = ['n' => $i + 1, 'text' => $lines[$i], 'hit' => ($i + 1) === $line];
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
        foreach ($e->getTrace() as $f) {
            $where = ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '') . '()';
            $file  = $f['file'] ?? '[internal]';
            $line  = $f['line'] ?? '';
            $rel   = $root !== '' && is_string($file) && str_starts_with($file, $root)
                ? substr($file, strlen($root)) : $file;

            $frames[] = [
                'where'   => $where === '()' ? '{main}' : $where,
                'file'    => $file,
                'rel'     => $rel,
                'line'    => $line,
                'kind'    => $this->frameKind($file),
                'ide'     => is_string($file) && $line !== ''
                    ? $this->ideLink($file, (int) $line)
                    : null,
                'snippet' => is_string($file) && is_int($line) && $line > 0
                    ? $this->codeAroundLines($file, $line, 3)
                    : [],
            ];
        }
        return $frames;
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
                'query'  => $_GET,
                'input'  => Request::method() === 'get' ? [] : Request::all(),
                'ip'     => $_SERVER['REMOTE_ADDR'] ?? '—',
            ];
        } catch (\Throwable) {
            return [
                'method' => $_SERVER['REQUEST_METHOD'] ?? '—',
                'uri'    => $_SERVER['REQUEST_URI'] ?? '—',
                'route'  => '—',
                'query'  => $_GET,
                'input'  => [],
                'ip'     => $_SERVER['REMOTE_ADDR'] ?? '—',
            ];
        }
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

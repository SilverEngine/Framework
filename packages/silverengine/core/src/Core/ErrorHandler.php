<?php
declare(strict_types=1);

namespace Silver\Core;

use Silver\Core\Bootstrap\Facades\Request;
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
     * Flatten a throwable's trace into display frames:
     * `where` = Class::method() / function(), plus file:line.
     *
     * @return list<array{where:string,file:string,line:int|string}>
     */
    private function normalizeFrames(\Throwable $e): array
    {
        $frames = [];
        foreach ($e->getTrace() as $f) {
            $where = ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '') . '()';
            $frames[] = [
                'where' => $where === '()' ? '{main}' : $where,
                'file'  => $f['file'] ?? '[internal]',
                'line'  => $f['line'] ?? '',
            ];
        }
        return $frames;
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
                        ->with('line', $e->getLine())
                        ->with('code_around', $this->codeAround($e))
                        ->with('frames', $this->normalizeFrames($orig))
                        ->with('request', $this->requestContext())
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

<?php
declare(strict_types=1);

namespace Silver\Core;

use Silver\Core\Bootstrap\Facades\Request;
use Silver\Exception\NotFoundException;
use Silver\Exception\ErrorException;
use Silver\Exception\Exception;
use Silver\Http\View;

final class ErrorHandler
{
    private static int $filter = E_ALL;

    public static function setFilter(int $filter): void
    {
        self::$filter = $filter;
    }

    public static function getFilter(): int
    {
        return self::$filter;
    }

    public static function withFilter(int $filter, callable $cb): mixed
    {
        $old = self::getFilter();
        self::setFilter($filter);
        $rv = $cb();
        self::setFilter($old);
        return $rv;
    }

    public static function handle_error(int $code, string $message, string $file, int $line): void
    {
        self::resetCWD();
        if ($code & self::$filter) {
            $ex = new ErrorException($message, $code);
            $ex->setFile($file);
            $ex->setLine($line);
            throw $ex;
        }
    }

    public static function handle_fatal(): void
    {
        self::resetCWD();
        if ($fatal = error_get_last()) {
            $ex = new Exception($fatal['message']);
            $ex->setFile($fatal['file']);
            $ex->setLine($fatal['line']);
            self::handle_ex($ex);
        }
    }

    public static function handle_ex(\Throwable $ex): void
    {
        self::resetCWD();
        if ($ex instanceof Exception) {
            self::render($ex, true);
        } else {
            $wrapped = new Exception($ex->getMessage(), (int) $ex->getCode());
            $wrapped->setFile($ex->getFile());
            $wrapped->setLine($ex->getLine());
            self::render($wrapped, true);
        }
    }

    private static function codeAround(Exception $ex, int $around = 3): string
    {
        $file = $ex->getFile();
        $line = $ex->getLine();

        if (file_exists($file)) {
            return implode("\n", array_slice(file($file), $line - $around, $around * 2 + 1));
        }

        return "Not a file: '" . print_r($file, true) . "'";
    }

    public static function render(Exception $e, bool $finalize = false): mixed
    {
        $view = null;

        if (Request::segment(1) === 'api' || (Request::segment(1) === 'public' && Request::segment(2) === 'api')) {
            try {
                if ($e instanceof NotFoundException) {
                    $payload = [
                        'data' => [
                            'message' => $e->getMessage(),
                            'code'    => $e->getCode() ?: 404,
                            'debug'   => [
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                            ],
                        ],
                    ];
                    header('Content-type: Application/json');
                    echo json_encode($payload);
                    exit();
                }

                header('Content-type: Application/json');
                $payload = [
                    'data' => [
                        'message' => $e->getMessage(),
                        'code'    => 500,
                        'file'    => $e->getFile(),
                        'on line' => $e->getLine(),
                        'trace'   => $e->getTrace(),
                    ],
                ];
                echo json_encode($payload);
                exit();
            } catch (\Throwable $e2) {
                self::finalize("Fatal error: " . $e2->getMessage());
            }
        } else {
            try {
                if ($e instanceof NotFoundException) {
                    $view = View::make('errors.404')
                        ->with('message', $e->getMessage())
                        ->with('debug', self::isDebug());
                } else {
                    $view = View::make('errors.500')
                        ->with('message', $e->getMessage())
                        ->with('file', $e->getFile())
                        ->with('line', $e->getLine())
                        ->with('code_around', self::codeAround($e))
                        ->with('back_trace', $e->getTrace())
                        ->with('debug', self::isDebug());
                }

                if ($finalize) {
                    self::finalize($view);
                } else {
                    return $view;
                }
            } catch (\Throwable $e2) {
                self::finalize("Fatal error: " . $e2->getMessage());
            }
        }

        return null;
    }

    private static function finalize(mixed $content): never
    {
        http_response_code(500);

        if ($content instanceof View) {
            echo $content->render();
        } else {
            echo $content;
        }
        exit;
    }

    private static function isDebug(): bool
    {
        return (bool) Env::get('debug', false);
    }

    private static function resetCWD(): void
    {
        if (defined('ROOT')) {
            chdir(ROOT);
        }
    }
}

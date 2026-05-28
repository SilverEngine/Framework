<?php
declare(strict_types=1);

namespace Silver\App\Middlewares;

use Silver\Core\Contracts\MiddlewareInterface;
use Silver\Core\ErrorHandler as Handler;
use Silver\Exception\NotFoundException;
use Silver\Http\AuthorizationException;
use Silver\Http\Csrf\CsrfTokenMismatchException;
use Silver\Http\Request;
use Silver\Http\Response;
use Silver\Http\Session;
use Silver\Http\ValidationException;
use Closure;

final class ErrorHandler implements MiddlewareInterface
{
    public function __construct(private readonly Handler $handler) {}

    public function execute(Request $req, Response $res, Closure $next): mixed
    {
        try {
            return $next();
        } catch (ValidationException $e) {
            return $this->renderValidation($req, $res, $e);
        } catch (AuthorizationException $e) {
            return $this->renderAuthorization($req, $res, $e);
        } catch (CsrfTokenMismatchException $e) {
            return $this->renderCsrf($req, $res, $e);
        } catch (NotFoundException $e) {
            $res->setCode(404);
            return $this->handler->render($e);
        } catch (\Throwable $e) {
            $res->setCode(500);
            // Keep the original throwable as `previous` so the error
            // page shows its real class, file/line and stack trace
            // (the wrapper's own trace would be useless).
            $wrapped = new \Silver\Exception\Exception($e->getMessage(), (int) $e->getCode(), $e);
            $wrapped->setFile($e->getFile());
            $wrapped->setLine($e->getLine());
            return $this->handler->render($wrapped);
        }
    }

    private function renderValidation(Request $req, Response $res, ValidationException $e): mixed
    {
        if ($req->wantsJson()) {
            $res->setCode(422);
            $res->setHeader('Content-Type', 'application/json; charset=utf-8');
            return (string) json_encode([
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ]);
        }

        Session::flash('_errors', $e->errors());
        if ($e->oldInput !== null) {
            Session::flash('_old', $e->oldInput);
        }

        $back = $_SERVER['HTTP_REFERER'] ?? '/';
        $res->setCode(302);
        $res->setHeader('Location', $back);
        return '';
    }

    private function renderAuthorization(Request $req, Response $res, AuthorizationException $e): mixed
    {
        if ($req->wantsJson()) {
            $res->setCode(403);
            $res->setHeader('Content-Type', 'application/json; charset=utf-8');
            return (string) json_encode(['message' => $e->getMessage()]);
        }
        $res->setCode(403);
        return $this->handler->render($e);
    }

    private function renderCsrf(Request $req, Response $res, CsrfTokenMismatchException $e): mixed
    {
        if ($req->wantsJson()) {
            $res->setCode(419);
            $res->setHeader('Content-Type', 'application/json; charset=utf-8');
            return (string) json_encode(['message' => $e->getMessage()]);
        }
        $res->setCode(419);
        return $this->handler->render($e);
    }
}

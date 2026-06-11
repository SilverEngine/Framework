<?php
declare(strict_types=1);

namespace Silver\App\Middlewares;

use Silver\Core\Contracts\MiddlewareInterface;
use Silver\Core\ErrorHandler as Handler;
use Silver\Exception\NotFoundException;
use Silver\Auth\AuthenticationException;
use Silver\Auth\ThrottleRequestsException;
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
        } catch (AuthenticationException $e) {
            return $this->renderAuthentication($req, $res, $e);
        } catch (AuthorizationException $e) {
            return $this->renderAuthorization($req, $res, $e);
        } catch (CsrfTokenMismatchException $e) {
            return $this->renderCsrf($req, $res, $e);
        } catch (ThrottleRequestsException $e) {
            return $this->renderThrottle($req, $res, $e);
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
        // Wisp (Inertia) wants the redirect-back dance, NOT a JSON
        // envelope: errors travel via the session flash and surface as
        // the page object's `errors` prop on the follow-up GET — that's
        // what useForm consumes. 303 so mutating verbs re-request as GET.
        if ($this->isWisp($req)) {
            $this->flashValidation($e);
            $res->setCode(303);
            $res->setHeader('Location', $_SERVER['HTTP_REFERER'] ?? '/');
            return '';
        }

        if ($req->wantsJson()) {
            $res->setCode(422);
            $res->setHeader('Content-Type', 'application/json; charset=utf-8');
            return (string) json_encode([
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ]);
        }

        $this->flashValidation($e);
        $res->setCode(302);
        $res->setHeader('Location', $_SERVER['HTTP_REFERER'] ?? '/');
        return '';
    }

    private function flashValidation(ValidationException $e): void
    {
        Session::flash('_errors', $e->errors());
        if ($e->oldInput !== null) {
            Session::flash('_old', $e->oldInput);
        }
    }

    private function isWisp(Request $req): bool
    {
        return $req->hasHeader('X-Inertia');
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

    private function renderAuthentication(Request $req, Response $res, AuthenticationException $e): mixed
    {
        // Wisp clients follow redirects natively — send them to the
        // login page instead of a 401 JSON they'd render as a modal.
        if ($this->isWisp($req)) {
            $res->setCode(302);
            $res->setHeader('Location', $e->redirectTo ?? '/login');
            return '';
        }

        if ($req->wantsJson()) {
            $res->setCode(401);
            $res->setHeader('Content-Type', 'application/json; charset=utf-8');
            return (string) json_encode(['message' => $e->getMessage()]);
        }
        $res->setCode(302);
        $res->setHeader('Location', $e->redirectTo ?? '/login');
        return '';
    }

    private function renderThrottle(Request $req, Response $res, ThrottleRequestsException $e): mixed
    {
        $res->setCode(429);
        $res->setHeader('Retry-After', (string) $e->retryAfter);
        if ($req->wantsJson()) {
            $res->setHeader('Content-Type', 'application/json; charset=utf-8');
            return (string) json_encode(['message' => $e->getMessage(), 'retry_after' => $e->retryAfter]);
        }
        return $this->handler->render($e);
    }
}

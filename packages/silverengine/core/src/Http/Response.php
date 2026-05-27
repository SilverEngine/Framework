<?php
declare(strict_types=1);

namespace Silver\Http;

use Silver\Core\AppInstanceTrait;
use Silver\Core\Contracts\Http\ResponseInterface;
use Silver\Core\Contracts\RenderInterface;
use Silver\Core\Env;

class Response implements ResponseInterface
{
    use AppInstanceTrait;

    private int $code = 200;
    private array $headers = [];
    private array $cookies = [];
    private mixed $body = null;

    public function setHeader(string $key, string $value): void
    {
        $this->headers[$key] = $value;
    }

    public function json(string $payload): mixed
    {
        header('Content-type: Application/json');
        return json_decode($payload);
    }

    public function xml(string $data): string
    {
        return " $data ";
    }

    public function getHeader(string $key, mixed $default = null): mixed
    {
        return $this->headers[$key] ?? $default;
    }

    public function setCode(int $code): void
    {
        $this->code = $code;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function setCookie(string $name, string $value, int $expiration): void
    {
        $this->cookies[$name] = [$value, $expiration];
    }

    public function setBody(mixed $body): void
    {
        if (!is_string($body)
            && !($body instanceof RenderInterface)
            && !is_array($body)
            && !is_object($body)
        ) {
            throw new \Exception("Unknown body type.");
        }

        $this->body = $body;
    }

    public function send(): void
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '*/*';
        $types = preg_split('/[,;] */', $accept);

        $contentType = array_find(
            $types,
            static fn (string $type): bool => in_array($type, ['application/json', 'text/html', 'text/*', '*/*'], true),
        );

        if ($contentType === null) {
            http_response_code(406);
            return;
        }

        if (Env::get('debug') && Request::instance()?->input('__override_json__')) {
            $contentType = 'application/json';
            $this->setHeader('Content-Type', 'application/json');
        }

        // Respect an explicit Content-Type set by the controller — don't
        // clobber it with the Accept-negotiated value (and never emit the
        // useless literal "*/*" when the client expressed no preference).
        if (!isset($this->headers['Content-Type'])) {
            $this->setHeader(
                'Content-Type',
                $contentType === '*/*' ? 'text/html' : $contentType,
            );
        }

        // Whatever ended up in the outbound header drives the body dispatch
        // below — strip parameters like "; charset=utf-8" so the match()
        // arms keep matching the bare MIME.
        $contentType = strtok($this->headers['Content-Type'], ';') ?: $contentType;

        if (defined('APP_START')) {
            $totalMs = (hrtime(true) - APP_START) / 1e6;
            $this->setHeader('X-Response-Time', number_format($totalMs, 2) . 'ms');
        }

        http_response_code($this->code);
        foreach ($this->headers as $key => $value) {
            header($key . ': ' . $value);
        }

        foreach ($this->cookies as $name => [$value, $expiration]) {
            setcookie($name, $value, $expiration);
        }

        if ($this->body === null) {
            return;
        }

        $body = $this->body;

        $timeComment = defined('APP_START')
            ? "\n<!-- Page generated in " . number_format((hrtime(true) - APP_START) / 1e6, 2) . "ms -->"
            : '';

        // Wisp: serve the page object as JSON on Inertia navigations,
        // or the full Ghost shell on a fresh/full page load.
        if ($body instanceof \Silver\Engine\Ghost\WispResponse) {
            header('Vary: X-Inertia');

            if (!empty($_SERVER['HTTP_X_INERTIA'])) {
                header('Content-Type: application/json');
                header('X-Inertia: true');
                print json_encode($body->data());
            } else {
                print $body->render() . $timeComment;
            }

            return;
        }

        match ($contentType) {
            '*/*', 'text/*', 'text/html' => match (true) {
                is_string($body), is_numeric($body) => print($body . $timeComment),
                $body instanceof RenderInterface => print($body->render() . $timeComment),
                is_array($body), is_object($body) => print(json_encode($body)),
                default => throw new \Exception("FATAL: Unknown body type."),
            },
            'application/json' => print(match (true) {
                // Already-serialized JSON from a controller — pass through.
                is_string($body)                  => $body,
                $body instanceof RenderInterface  => json_encode($body->data()),
                default                           => json_encode($body),
            }),
            default => throw new \Exception('Unsupported content type: ' . $contentType),
        };
    }
}

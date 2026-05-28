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

    /**
     * @param array{path?:string,domain?:string,secure?:bool,httponly?:bool,samesite?:string} $options
     */
    public function setCookie(string $name, string $value, int $expiration, array $options = []): void
    {
        $this->cookies[$name] = [$value, $expiration, $options];
    }

    /**
     * Configure the response as JSON and return the encoded body.
     *
     * Centralises the three-step ritual (set code → set Content-Type →
     * json_encode) so controllers don't each re-derive it. The encoded
     * string is returned so callers can `return $response->json(...)`
     * directly from `__invoke()`. Already-encoded strings pass through
     * verbatim — letting controllers hand-craft pretty JSON without it
     * getting double-encoded.
     *
     * @param int $flags  json_encode flags (defaults match what every
     *                    existing call site was already using)
     */
    public function json(
        mixed $body,
        int $code = 200,
        int $flags = JSON_UNESCAPED_SLASHES,
    ): string {
        $this->setCode($code);
        $this->setHeader('Content-Type', 'application/json; charset=utf-8');
        return is_string($body) ? $body : (string) json_encode($body, $flags);
    }

    /**
     * Configure the response as XML and return the encoded body. Mirrors
     * {@see self::json()} for clients that prefer XML (legacy SOAP-ish
     * partners, RSS/Atom feeds, etc.).
     *
     * Accepts:
     *   - pre-rendered XML string  → passed through verbatim
     *   - SimpleXMLElement         → serialized via asXML()
     *   - array                    → recursively serialized under $rootElement
     */
    public function xml(
        string|array|\SimpleXMLElement $body,
        int $code = 200,
        string $rootElement = 'response',
    ): string {
        $this->setCode($code);
        $this->setHeader('Content-Type', 'application/xml; charset=utf-8');

        if (is_string($body)) {
            return $body;
        }
        if ($body instanceof \SimpleXMLElement) {
            return (string) $body->asXML();
        }

        $root = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><' . $rootElement . '/>');
        self::arrayToXml($body, $root);
        return (string) $root->asXML();
    }

    /** Recursively append array entries as children of $parent. */
    private static function arrayToXml(array $data, \SimpleXMLElement $parent): void
    {
        foreach ($data as $key => $value) {
            // Numeric keys become <item index="N"> so the XML stays well-formed.
            if (is_int($key)) {
                $child = $parent->addChild('item');
                $child->addAttribute('index', (string) $key);
            } else {
                // Element names must start with a letter/underscore — sanitise.
                $name = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $key) ?: 'field';
                if (!preg_match('/^[A-Za-z_]/', $name)) {
                    $name = '_' . $name;
                }
                $child = $parent->addChild($name);
            }

            if (is_array($value)) {
                self::arrayToXml($value, $child);
            } elseif (is_object($value)) {
                self::arrayToXml((array) $value, $child);
            } else {
                // SimpleXML refuses raw null; cast everything to scalar.
                $child[0] = (string) ($value ?? '');
            }
        }
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
            static fn (string $type): bool => in_array(
                $type,
                ['application/json', 'application/xml', 'text/xml', 'text/html', 'text/*', '*/*'],
                true,
            ),
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

        foreach ($this->cookies as $name => $entry) {
            $value      = $entry[0];
            $expiration = $entry[1];
            $options    = $entry[2] ?? [];
            if ($options === []) {
                setcookie($name, $value, $expiration);
            } else {
                setcookie($name, $value, array_merge(['expires' => $expiration], $options));
            }
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
            'application/xml', 'text/xml' => print(match (true) {
                // Already-serialized XML from a controller — pass through.
                is_string($body)                 => $body,
                $body instanceof \SimpleXMLElement => $body->asXML(),
                $body instanceof RenderInterface => $this->xml($body->data()),
                is_array($body), is_object($body) => $this->xml((array) $body),
                default => throw new \Exception('Unsupported XML body type.'),
            }),
            default => throw new \Exception('Unsupported content type: ' . $contentType),
        };
    }
}

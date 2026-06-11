<?php
declare(strict_types=1);

namespace Silver\Mail\Drivers;

use Silver\Mail\MailException;

/**
 * fsockopen-backed {@see SmtpConnection}. `ssl` encryption opens an
 * implicit-TLS socket (ssl://, port 465 style); STARTTLS upgrades via
 * {@see startTls()} when the driver asks. Multiline replies (250-…)
 * are drained; the last line is returned.
 */
final class SocketSmtpConnection implements SmtpConnection
{
    /** @var resource|null */
    private $stream = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly bool $implicitTls = false,
        private readonly int $timeout = 10,
    ) {}

    public function open(): string
    {
        $target = ($this->implicitTls ? 'ssl://' : '') . $this->host;
        $stream = @fsockopen($target, $this->port, $errno, $error, $this->timeout);
        if ($stream === false) {
            throw new MailException("SMTP connect to {$this->host}:{$this->port} failed: {$error}");
        }
        stream_set_timeout($stream, $this->timeout);
        $this->stream = $stream;
        return $this->readReply();
    }

    public function command(string $line): string
    {
        if ($this->stream === null) {
            throw new MailException('SMTP connection not open.');
        }
        fwrite($this->stream, $line . "\r\n");
        return $this->readReply();
    }

    public function startTls(): void
    {
        if ($this->stream === null) {
            throw new MailException('SMTP connection not open.');
        }
        $ok = stream_socket_enable_crypto(
            $this->stream,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT,
        );
        if ($ok !== true) {
            throw new MailException('STARTTLS negotiation failed.');
        }
    }

    public function close(): void
    {
        if ($this->stream !== null) {
            @fclose($this->stream);
            $this->stream = null;
        }
    }

    private function readReply(): string
    {
        $line = '';
        // "250-EHLO continuation" lines have a dash after the code;
        // the final line uses a space.
        while ($this->stream !== null && ($raw = fgets($this->stream)) !== false) {
            $line = rtrim($raw, "\r\n");
            if (!isset($line[3]) || $line[3] !== '-') {
                break;
            }
        }
        return $line;
    }
}

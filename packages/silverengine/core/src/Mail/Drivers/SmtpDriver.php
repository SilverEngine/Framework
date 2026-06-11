<?php
declare(strict_types=1);

namespace Silver\Mail\Drivers;

use Silver\Mail\Contracts\MailDriver;
use Silver\Mail\MailException;
use Silver\Mail\Message;

/**
 * Hand-rolled SMTP client (dependency-light runtime: no symfony/
 * mailer). Speaks EHLO → [STARTTLS] → [AUTH LOGIN] → MAIL FROM →
 * RCPT TO (every to/cc/bcc) → DATA → QUIT. A 4xx/5xx reply anywhere
 * throws MailException; the connection always closes.
 *
 * encryption: ''    plain socket
 *             'tls' STARTTLS upgrade after the first EHLO
 *             'ssl' implicit TLS (handled by the connection itself)
 */
final class SmtpDriver implements MailDriver
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username = '',
        private readonly string $password = '',
        private readonly string $encryption = '',
        private readonly int $timeout = 10,
        private ?SmtpConnection $connection = null,
    ) {}

    public function send(Message $message): bool
    {
        $conn = $this->connection ??= new SocketSmtpConnection(
            $this->host,
            $this->port,
            implicitTls: $this->encryption === 'ssl',
            timeout: $this->timeout,
        );

        try {
            $this->expectOk($conn->open(), 'greeting');
            $this->expectOk($conn->command('EHLO ' . (gethostname() ?: 'localhost')), 'EHLO');

            if ($this->encryption === 'tls') {
                $this->expectOk($conn->command('STARTTLS'), 'STARTTLS');
                $conn->startTls();
                $this->expectOk($conn->command('EHLO ' . (gethostname() ?: 'localhost')), 'EHLO (tls)');
            }

            if ($this->username !== '') {
                $this->expectOk($conn->command('AUTH LOGIN'), 'AUTH LOGIN');
                $this->expectOk($conn->command(base64_encode($this->username)), 'AUTH username');
                $this->expectOk($conn->command(base64_encode($this->password)), 'AUTH password');
            }

            $this->expectOk(
                $conn->command('MAIL FROM:<' . $message->fromAddress() . '>'),
                'MAIL FROM',
            );
            foreach ($message->recipients() as $rcpt) {
                $this->expectOk($conn->command("RCPT TO:<{$rcpt}>"), "RCPT TO {$rcpt}");
            }

            $this->expectOk($conn->command('DATA'), 'DATA');
            $this->expectOk($conn->command($this->payload($message)), 'message data');
            $conn->command('QUIT');

            return true;
        } finally {
            $conn->close();
        }
    }

    /** Headers + dot-stuffed body, terminated with the bare-dot line. */
    private function payload(Message $message): string
    {
        $mime  = $message->mime();
        $lines = [];
        foreach ($mime['headers'] as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }
        $lines[] = 'Subject: ' . $message->subject();
        $lines[] = 'Date: ' . date(DATE_RFC2822);
        $lines[] = '';
        $lines[] = $mime['body'];

        $data = implode("\r\n", $lines);
        // Dot-stuffing: a leading '.' on any line would end DATA early.
        $data = (string) preg_replace('/^\./m', '..', $data);

        return $data . "\r\n.";
    }

    private function expectOk(string $reply, string $phase): void
    {
        $code = (int) substr($reply, 0, 3);
        if ($code >= 400 || $code === 0) {
            throw new MailException("SMTP {$phase} failed: {$reply}");
        }
    }
}

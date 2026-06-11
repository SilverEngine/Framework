<?php
declare(strict_types=1);

namespace Silver\Mail\Drivers;

/**
 * Transport seam under {@see SmtpDriver} — the driver speaks the
 * protocol, the connection moves bytes. Tests substitute a scripted
 * fake; production uses {@see SocketSmtpConnection}.
 */
interface SmtpConnection
{
    /** Open the transport and return the server greeting line. */
    public function open(): string;

    /** Send one command line (no CRLF) and return the reply line. */
    public function command(string $line): string;

    /** Upgrade the open connection to TLS (after STARTTLS). */
    public function startTls(): void;

    public function close(): void;
}

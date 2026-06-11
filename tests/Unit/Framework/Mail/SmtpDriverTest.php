<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Mail;

use PHPUnit\Framework\TestCase;
use Silver\Mail\Drivers\SmtpConnection;
use Silver\Mail\Drivers\SmtpDriver;
use Silver\Mail\MailException;
use Silver\Mail\Message;

/**
 * Scripted SMTP server: returns canned replies, records every command
 * the driver writes — protocol coverage without a socket.
 */
final class FakeSmtpConnection implements SmtpConnection
{
    /** @var list<string> */
    public array $written = [];
    public bool $closed = false;

    /** @param array<string, string> $replies command-prefix → reply (default '250 OK') */
    public function __construct(
        private readonly string $greeting = '220 fake.test ESMTP',
        private array $replies = [],
    ) {}

    public function open(): string
    {
        return $this->greeting;
    }

    public function command(string $line): string
    {
        $this->written[] = $line;
        foreach ($this->replies as $prefix => $reply) {
            if (str_starts_with($line, $prefix)) {
                return $reply;
            }
        }
        if (str_starts_with($line, 'DATA')) {
            return '354 go ahead';
        }
        return '250 OK';
    }

    public function startTls(): void
    {
        $this->written[] = '(tls upgraded)';
    }

    public function close(): void
    {
        $this->closed = true;
    }
}

final class SmtpDriverTest extends TestCase
{
    private function message(): Message
    {
        return new Message()
            ->to('rcpt@example.test')
            ->cc('copy@example.test')
            ->from('noreply@app.test')
            ->subject('Hi')
            ->text('Body line.');
    }

    public function testHappyPathSpeaksTheProtocolInOrder(): void
    {
        $fake   = new FakeSmtpConnection();
        $driver = new SmtpDriver(
            host: 'smtp.test', port: 587,
            username: 'user', password: 'pw',
            connection: $fake,
        );

        self::assertTrue($driver->send($this->message()));

        $cmds = $fake->written;
        self::assertStringStartsWith('EHLO', $cmds[0]);
        self::assertSame('AUTH LOGIN', $cmds[1]);
        self::assertSame(base64_encode('user'), $cmds[2]);
        self::assertSame(base64_encode('pw'), $cmds[3]);
        self::assertSame('MAIL FROM:<noreply@app.test>', $cmds[4]);
        self::assertSame('RCPT TO:<rcpt@example.test>', $cmds[5]);
        self::assertSame('RCPT TO:<copy@example.test>', $cmds[6]);
        self::assertSame('DATA', $cmds[7]);
        self::assertStringContainsString('Subject: Hi', $cmds[8]);
        self::assertStringEndsWith("\r\n.", $cmds[8]);
        self::assertSame('QUIT', $cmds[9]);
        self::assertTrue($fake->closed);
    }

    public function testNoCredentialsSkipsAuth(): void
    {
        $fake   = new FakeSmtpConnection();
        $driver = new SmtpDriver(host: 'smtp.test', port: 25, connection: $fake);

        $driver->send($this->message());

        self::assertSame('MAIL FROM:<noreply@app.test>', $fake->written[1]);
    }

    public function testRejectedRecipientThrows(): void
    {
        $fake = new FakeSmtpConnection(replies: ['RCPT TO' => '550 no such user']);
        $driver = new SmtpDriver(host: 'smtp.test', port: 25, connection: $fake);

        $this->expectException(MailException::class);
        $driver->send($this->message());
    }

    public function testBadGreetingThrows(): void
    {
        $fake = new FakeSmtpConnection(greeting: '421 service not available');
        $driver = new SmtpDriver(host: 'smtp.test', port: 25, connection: $fake);

        $this->expectException(MailException::class);
        $driver->send($this->message());
    }
}

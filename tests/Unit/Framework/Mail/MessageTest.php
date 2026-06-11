<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Mail;

use PHPUnit\Framework\TestCase;
use Silver\Mail\Message;

final class MessageTest extends TestCase
{
    public function testTextOnlyMessage(): void
    {
        $m = new Message()
            ->to('lex@example.test')
            ->from('noreply@app.test', 'App')
            ->subject('Hello')
            ->text('Plain words.');

        $mime = $m->mime();

        self::assertSame('App <noreply@app.test>', $mime['headers']['From']);
        self::assertStringContainsString('text/plain', $mime['headers']['Content-Type']);
        self::assertStringContainsString('Plain words.', $mime['body']);
        self::assertSame('Hello', $m->subject());
        self::assertSame(['lex@example.test'], $m->recipients());
    }

    public function testHtmlAndTextBecomeMultipartAlternative(): void
    {
        $m = new Message()
            ->to('lex@example.test')
            ->from('noreply@app.test')
            ->subject('Hi')
            ->text('plain version')
            ->html('<h1>rich version</h1>');

        $mime = $m->mime();

        self::assertStringContainsString('multipart/alternative', $mime['headers']['Content-Type']);
        self::assertStringContainsString('plain version', $mime['body']);
        self::assertStringContainsString('<h1>rich version</h1>', $mime['body']);
        // Plain part must come first so clients prefer the html part.
        self::assertLessThan(
            strpos($mime['body'], '<h1>'),
            strpos($mime['body'], 'plain version'),
        );
    }

    public function testAttachmentMakesMultipartMixedWithBase64(): void
    {
        $m = new Message()
            ->to('lex@example.test')
            ->from('noreply@app.test')
            ->subject('Report')
            ->text('see attached')
            ->attachData('col1,col2', 'report.csv', 'text/csv');

        $mime = $m->mime();

        self::assertStringContainsString('multipart/mixed', $mime['headers']['Content-Type']);
        self::assertStringContainsString('text/csv', $mime['body']);
        self::assertStringContainsString('filename="report.csv"', $mime['body']);
        self::assertStringContainsString(base64_encode('col1,col2'), $mime['body']);
    }

    public function testAttachFromDisk(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mml');
        file_put_contents($path, '%PDF-fake');

        try {
            $m = new Message()
                ->to('a@b.test')->from('c@d.test')->subject('s')->text('b')
                ->attach($path, 'invoice.pdf', 'application/pdf');

            $body = $m->mime()['body'];
            self::assertStringContainsString('filename="invoice.pdf"', $body);
            self::assertStringContainsString(base64_encode('%PDF-fake'), $body);
        } finally {
            @unlink($path);
        }
    }

    public function testCcBccAndMultipleRecipients(): void
    {
        $m = new Message()
            ->to('a@x.test')
            ->to('b@x.test', 'Bee')
            ->cc('c@x.test')
            ->bcc('d@x.test')
            ->from('noreply@app.test')
            ->subject('s')
            ->text('b');

        $mime = $m->mime();

        self::assertSame('a@x.test, Bee <b@x.test>', $mime['headers']['To']);
        self::assertSame('c@x.test', $mime['headers']['Cc']);
        self::assertArrayNotHasKey('Bcc', $mime['headers']); // bcc never serialized into headers
        self::assertSame(['a@x.test', 'b@x.test', 'c@x.test', 'd@x.test'], $m->recipients());
    }

    public function testHeaderInjectionIsStripped(): void
    {
        $m = new Message()
            ->to('a@x.test', "Evil\r\nBcc: spam@x.test")
            ->from('noreply@app.test', "Bad\nName")
            ->subject("Hello\r\nX-Injected: yes")
            ->text('b');

        $mime = $m->mime();

        self::assertStringNotContainsString("\n", $mime['headers']['To']);
        self::assertStringNotContainsString("\n", $mime['headers']['From']);
        self::assertStringNotContainsString("\n", $m->subject());
    }

    public function testInvalidAddressThrows(): void
    {
        $this->expectException(\Throwable::class);
        new Message()->to('not-an-email');
    }
}

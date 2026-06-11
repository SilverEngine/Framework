<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Mail;

use PHPUnit\Framework\TestCase;
use Silver\Mail\Drivers\ArrayDriver;
use Silver\Mail\Drivers\LogDriver;
use Silver\Mail\Mailer;
use Silver\Mail\Message;

final class MailerDriversTest extends TestCase
{
    private function message(): Message
    {
        return new Message()
            ->to('lex@example.test')
            ->subject('Welcome')
            ->text('Hello there.');
    }

    public function testArrayDriverCollectsSentMessages(): void
    {
        $driver = new ArrayDriver();
        $sent   = $this->message()->from('noreply@app.test');

        self::assertTrue($driver->send($sent));
        self::assertCount(1, $driver->sent());
        self::assertSame($sent, $driver->sent()[0]);
        self::assertTrue($driver->hasSentTo('lex@example.test'));
        self::assertFalse($driver->hasSentTo('other@example.test'));
    }

    public function testLogDriverAppendsRenderedMessage(): void
    {
        $path   = sys_get_temp_dir() . '/silver_mail_' . getmypid() . '.log';
        @unlink($path);

        try {
            $driver = new LogDriver($path);
            self::assertTrue($driver->send($this->message()->from('noreply@app.test')));

            $log = (string) file_get_contents($path);
            self::assertStringContainsString('To: lex@example.test', $log);
            self::assertStringContainsString('Subject: Welcome', $log);
            self::assertStringContainsString('Hello there.', $log);
        } finally {
            @unlink($path);
        }
    }

    public function testFileDriverWritesOneEmlPerMessage(): void
    {
        $dir = sys_get_temp_dir() . '/silver_mail_dir_' . getmypid();

        try {
            $driver = new \Silver\Mail\Drivers\FileDriver($dir);
            self::assertTrue($driver->send($this->message()->from('noreply@app.test')));
            self::assertTrue($driver->send($this->message()->from('noreply@app.test')));

            $files = glob($dir . '/*.eml') ?: [];
            self::assertCount(2, $files);

            $eml = (string) file_get_contents($files[0]);
            self::assertStringContainsString('To: lex@example.test', $eml);
            self::assertStringContainsString('Subject: Welcome', $eml);
            self::assertStringContainsString('Hello there.', $eml);
        } finally {
            foreach (glob($dir . '/*') ?: [] as $f) { @unlink($f); }
            @rmdir($dir);
        }
    }

    public function testMailerAppliesDefaultFromAndDelegates(): void
    {
        $driver = new ArrayDriver();
        $mailer = new Mailer($driver, fromAddress: 'noreply@app.test', fromName: 'App');

        self::assertTrue($mailer->send($this->message()));

        $sent = $driver->sent()[0];
        self::assertSame('App <noreply@app.test>', $sent->mime()['headers']['From']);
    }

    public function testExplicitFromWinsOverMailerDefault(): void
    {
        $driver = new ArrayDriver();
        $mailer = new Mailer($driver, fromAddress: 'noreply@app.test');

        $mailer->send($this->message()->from('ceo@app.test', 'The CEO'));

        self::assertSame('The CEO <ceo@app.test>', $driver->sent()[0]->mime()['headers']['From']);
    }

    public function testMailerWithoutAnyFromThrows(): void
    {
        $mailer = new Mailer(new ArrayDriver());

        $this->expectException(\Throwable::class);
        $mailer->send($this->message());
    }
}

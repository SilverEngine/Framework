<?php
declare(strict_types=1);

namespace Silver\Mail\Drivers;

use Silver\FileSystem\LocalFileSystem;
use Silver\Mail\Contracts\MailDriver;
use Silver\Mail\Message;

/**
 * Writes each message as its own RFC-822-style .eml file into a
 * directory — unlike {@see LogDriver}'s single append-log, every mail
 * is a separate openable file (drag into a mail client, attach to a
 * bug report, inspect attachments).
 */
final class FileDriver implements MailDriver
{
    public function __construct(
        private readonly string $directory,
    ) {}

    public function send(Message $message): bool
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

        $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.eml';

        $fs = new LocalFileSystem();
        $fs->mkdir($this->directory);
        return $fs->write(rtrim($this->directory, '/') . '/' . $name, implode("\r\n", $lines)) > 0;
    }
}

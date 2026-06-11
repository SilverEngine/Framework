<?php
declare(strict_types=1);

namespace Silver\Mail\Drivers;

use Silver\FileSystem\LocalFileSystem;
use Silver\Mail\Contracts\MailDriver;
use Silver\Mail\Message;

/**
 * Appends every message (headers + body) to one log file — the safe
 * dev default: nothing leaves the machine, everything is inspectable.
 */
final class LogDriver implements MailDriver
{
    public function __construct(
        private readonly string $path,
    ) {}

    public function send(Message $message): bool
    {
        $mime  = $message->mime();
        $lines = ['--- ' . date('c') . ' ---'];
        foreach ($mime['headers'] as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }
        $lines[] = 'Subject: ' . $message->subject();
        $lines[] = '';
        $lines[] = $mime['body'];
        $lines[] = '';

        $fs = new LocalFileSystem();
        $fs->mkdir(dirname($this->path));
        return $fs->append($this->path, implode("\n", $lines)) > 0;
    }
}

<?php
declare(strict_types=1);

namespace Silver\Mail\Drivers;

use Silver\Mail\Contracts\MailDriver;
use Silver\Mail\Message;

/**
 * PHP mail() transport (the platform's sendmail binary / SMTP ini
 * settings). To and Subject travel as mail() arguments; the remaining
 * MIME headers ride the additional-headers string.
 */
final class SendmailDriver implements MailDriver
{
    public function send(Message $message): bool
    {
        $mime = $message->mime();

        $to = $mime['headers']['To'];
        $headerLines = [];
        foreach ($mime['headers'] as $name => $value) {
            if ($name === 'To') {
                continue; // mail() first argument
            }
            $headerLines[] = "{$name}: {$value}";
        }

        return mail($to, $message->subject(), $mime['body'], implode("\r\n", $headerLines));
    }
}

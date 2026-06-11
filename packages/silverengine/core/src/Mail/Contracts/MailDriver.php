<?php
declare(strict_types=1);

namespace Silver\Mail\Contracts;

use Silver\Mail\Message;

/**
 * Transport strategy for outgoing mail. Implementations: Sendmail
 * (PHP mail()), Smtp (raw socket), Log (append to a file), Array
 * (in-memory, for tests). Throw {@see \Silver\Mail\MailException} on
 * transport failure; return true on acceptance.
 */
interface MailDriver
{
    public function send(Message $message): bool;
}

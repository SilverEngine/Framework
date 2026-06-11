<?php
declare(strict_types=1);

namespace Silver\Mail\Drivers;

use Silver\Mail\Contracts\MailDriver;
use Silver\Mail\Message;

/**
 * In-memory transport — the test fake. Nothing leaves the process;
 * assert against sent() / hasSentTo().
 */
final class ArrayDriver implements MailDriver
{
    /** @var list<Message> */
    private array $sent = [];

    public function send(Message $message): bool
    {
        $this->sent[] = $message;
        return true;
    }

    /** @return list<Message> */
    public function sent(): array
    {
        return $this->sent;
    }

    public function hasSentTo(string $address): bool
    {
        return array_any(
            $this->sent,
            static fn (Message $m): bool => in_array($address, $m->recipients(), true),
        );
    }

    public function flush(): void
    {
        $this->sent = [];
    }
}

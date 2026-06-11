<?php
declare(strict_types=1);

namespace Silver\Mail;

use Silver\Core\Env;
use Silver\Core\RuntimeContext;
use Silver\Mail\Contracts\MailDriver;
use Silver\Mail\Drivers\ArrayDriver;
use Silver\Mail\Drivers\FileDriver;
use Silver\Mail\Drivers\LogDriver;
use Silver\Mail\Drivers\SendmailDriver;
use Silver\Mail\Drivers\SmtpDriver;

/**
 * Mail service: applies the configured default From and hands the
 * message to the driver. Resolved as a container singleton from
 * config/mail.php; `mailer()` is the helper.
 *
 *   mailer()->send(
 *       new Message()->to($user->email)
 *           ->subject('Reset your password')
 *           ->view('emails/reset', ['url' => $url]),
 *   );
 */
final class Mailer
{
    public function __construct(
        private readonly MailDriver $driver,
        private readonly string $fromAddress = '',
        private readonly ?string $fromName = null,
    ) {}

    public function send(Message $message): bool
    {
        if (!$message->hasFrom()) {
            if ($this->fromAddress === '') {
                throw new MailException(
                    'Message has no From and no default is configured (mail.from.address).',
                );
            }
            $message->from($this->fromAddress, $this->fromName);
        }
        return $this->driver->send($message);
    }

    public function driver(): MailDriver
    {
        return $this->driver;
    }

    /** Build from config/mail.php — the container singleton factory. */
    public static function fromConfig(): self
    {
        $cfg  = (array) (Env::get('mail') ?? []);
        $from = (array) ($cfg['from'] ?? []);
        $root = RuntimeContext::current()->root;

        return new self(
            self::driverFor((string) ($cfg['driver'] ?? 'log'), $cfg, $root),
            (string) ($from['address'] ?? ''),
            isset($from['name']) && $from['name'] !== '' ? (string) $from['name'] : null,
        );
    }

    /** @param array<string, mixed> $cfg */
    private static function driverFor(string $name, array $cfg, string $root): MailDriver
    {
        $smtp = (array) ($cfg['smtp'] ?? []);

        return match ($name) {
            'sendmail' => new SendmailDriver(),
            'smtp'     => new SmtpDriver(
                host:       (string) ($smtp['host'] ?? 'localhost'),
                port:       (int) ($smtp['port'] ?? 587),
                username:   (string) ($smtp['username'] ?? ''),
                password:   (string) ($smtp['password'] ?? ''),
                encryption: (string) ($smtp['encryption'] ?? 'tls'),
                timeout:    (int) ($smtp['timeout'] ?? 10),
            ),
            'log'      => new LogDriver((string) ($cfg['log_path'] ?? $root . 'storage/Logs/mail.log')),
            'file'     => new FileDriver((string) ($cfg['file_path'] ?? $root . 'storage/mail')),
            'array'    => new ArrayDriver(),
            default    => throw new MailException("Unknown mail driver '{$name}'."),
        };
    }
}

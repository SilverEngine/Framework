<?php
declare(strict_types=1);

namespace Silver\Auth;

use Closure;
use Silver\Auth\Contracts\UserProvider;
use Silver\Mail\Mailer;
use Silver\Mail\Message;

/**
 * The two halves of "forgot my password":
 *
 *   sendResetLink($email)            mint a single-use token, mail the
 *                                    reset URL ("path?token&email")
 *   reset($email, $token, $newPw)    verify token ↔ email, re-hash,
 *                                    save, revoke the token
 *
 * The token plaintext only ever lives in the mail; the repository
 * stores sha256 (split-token scheme). One active token per email,
 * single use, TTL-bound.
 *
 * The default mail is a plain-text message with the relative reset
 * URL — override with {@see buildMailUsing()} to send a branded Ghost
 * view or an absolute URL.
 */
final class PasswordBroker
{
    /** @var (Closure(string, string): Message)|null fn (url, email) → Message */
    private ?Closure $mailBuilder = null;

    public function __construct(
        private readonly UserProvider $provider,
        private readonly PasswordResetRepository $tokens,
        private readonly Mailer $mailer,
        private readonly string $path = '/password/reset',
        private readonly int $ttlSeconds = 3600,
        private readonly string $emailField = 'email',
    ) {}

    /** @param Closure(string, string): Message $builder fn (url, email) → Message */
    public function buildMailUsing(Closure $builder): void
    {
        $this->mailBuilder = $builder;
    }

    /**
     * Mail a reset link. False when no account matches — callers
     * deciding to hide that (enumeration) is a presentation concern.
     */
    public function sendResetLink(string $email): bool
    {
        $user = $this->provider->retrieveByCredentials([$this->emailField => $email]);
        if ($user === null) {
            return false;
        }

        $token = $this->tokens->issue($email, $this->ttlSeconds);
        $url   = $this->path . '?' . http_build_query(['token' => $token, 'email' => $email]);

        $message = $this->mailBuilder !== null
            ? ($this->mailBuilder)($url, $email)
            : new Message()
                ->to($email)
                ->subject('Reset your password')
                ->text(
                    "A password reset was requested for this address.\r\n\r\n"
                    . "Reset link (valid for {$this->ttlSeconds} seconds, single use):\r\n"
                    . $url . "\r\n\r\n"
                    . 'If you did not request this, ignore this mail.',
                );

        return $this->mailer->send($message);
    }

    /** Verify token ↔ email, store the new hash, burn the token. */
    public function reset(string $email, string $token, string $newPassword): bool
    {
        if ($this->tokens->emailFor($token) !== $email) {
            return false;
        }

        $user = $this->provider->retrieveByCredentials([$this->emailField => $email]);
        if ($user === null) {
            return false;
        }

        if (property_exists($user, 'password')) {
            $user->password = Hash::make($newPassword);
            if (method_exists($user, 'save')) {
                $user->save();
            }
        }

        $this->tokens->revoke($token);
        return true;
    }
}

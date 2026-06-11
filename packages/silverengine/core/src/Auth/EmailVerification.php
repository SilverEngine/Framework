<?php
declare(strict_types=1);

namespace Silver\Auth;

use Silver\Auth\Contracts\Authenticatable;
use Silver\Auth\Contracts\MustVerifyEmail;
use Silver\Helpers\URL;
use Silver\Http\Request;

/**
 * Signed-URL email verification (reuses F7's URL::temporarySigned).
 *
 * The link carries the user id plus sha1 of the address it was
 * generated for — change the email and old links die, and a link for
 * user A can never verify user B. Sending the mail is the app's (or
 * the G3 mail service's) job; this class only mints and checks links.
 */
final class EmailVerification
{
    public function __construct(
        private readonly string $path = '/email/verify',
        private readonly int $ttlSeconds = 3600,
    ) {}

    public function verificationUrl(Authenticatable&MustVerifyEmail $user): string
    {
        return URL::temporarySigned($this->path, $this->ttlSeconds, [
            'id'   => $user->getAuthIdentifier(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);
    }

    /**
     * Validate the request's link against $user and mark them
     * verified. Already-verified users short-circuit true (idempotent
     * double-clicks).
     */
    public function verify(Request $request, Authenticatable&MustVerifyEmail $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return true;
        }
        if (!URL::hasValidSignature($request)) {
            return false;
        }
        if ((string) $request->query('id') !== (string) $user->getAuthIdentifier()) {
            return false;
        }
        if (!hash_equals(sha1($user->getEmailForVerification()), (string) $request->query('hash'))) {
            return false;
        }

        $user->markEmailAsVerified();
        return true;
    }
}

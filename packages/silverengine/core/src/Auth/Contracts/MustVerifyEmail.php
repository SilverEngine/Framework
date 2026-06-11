<?php
declare(strict_types=1);

namespace Silver\Auth\Contracts;

/**
 * Implemented by user models that require a verified email address.
 * The conventional backing column is a nullable `email_verified_at`
 * timestamp.
 */
interface MustVerifyEmail
{
    /** The address verification links are generated for. */
    public function getEmailForVerification(): string;

    public function hasVerifiedEmail(): bool;

    public function markEmailAsVerified(): void;
}

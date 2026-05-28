<?php
declare(strict_types=1);

namespace Silver\Auth\Contracts;

/**
 * Strategy for finding and validating users — the layer between the
 * {@see Guard} (storage-agnostic) and the actual persistence
 * (ORM model, LDAP, external API…).
 */
interface UserProvider
{
    public function retrieveById(int|string $id): ?Authenticatable;

    /** @param array<string, mixed> $credentials */
    public function retrieveByCredentials(array $credentials): ?Authenticatable;

    /** @param array<string, mixed> $credentials */
    public function validateCredentials(Authenticatable $user, array $credentials): bool;
}

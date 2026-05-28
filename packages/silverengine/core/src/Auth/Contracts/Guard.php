<?php
declare(strict_types=1);

namespace Silver\Auth\Contracts;

interface Guard
{
    public function check(): bool;

    public function guest(): bool;

    public function user(): ?Authenticatable;

    public function id(): int|string|null;

    /** @param array<string, mixed> $credentials */
    public function attempt(array $credentials): bool;

    /** @param array<string, mixed> $credentials */
    public function validate(array $credentials): bool;

    public function login(Authenticatable $user): void;

    public function logout(): void;
}

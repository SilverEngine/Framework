<?php

declare(strict_types=1);

namespace Silver\Http;

/**
 * Immutable result of one {@see Validator::check()} call. Holds the
 * per-field error map (`['email' => ['email is required!']]`) and gives
 * predicate-style accessors over it.
 *
 * Returning this instead of `[string, …]` lets callers ask the question
 * they actually care about (`->passes()`, `->forField('email')`) without
 * the framework holding on to global state — fixing the bug class where
 * two `check()` calls in the same request used to clobber each other.
 *
 * @phpstan-type ErrorMap array<string, list<string>>
 */
final readonly class ValidationResult
{
    /** @param ErrorMap $errors */
    public function __construct(private array $errors = [])
    {
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /**
     * Flat list of every error message across every field. Order follows
     * declaration order of the rules.
     *
     * @return list<string>
     */
    public function all(): array
    {
        return $this->errors === [] ? [] : array_merge(...array_values($this->errors));
    }

    /**
     * Errors raised against one specific field. Empty list when the field
     * passed (or wasn't validated at all).
     *
     * @return list<string>
     */
    public function forField(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    public function hasField(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /** @return ErrorMap full per-field error map */
    public function toArray(): array
    {
        return $this->errors;
    }
}

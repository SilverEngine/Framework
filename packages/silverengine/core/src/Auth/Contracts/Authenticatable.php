<?php
declare(strict_types=1);

namespace Silver\Auth\Contracts;

/**
 * Implemented by any model that can log in via {@see \Silver\Auth\Guard}.
 *
 * The framework's only assumption is "you have an identifier and a
 * hash to compare against" — everything else (column names, password
 * algorithm, profile fields) is the implementor's business.
 */
interface Authenticatable
{
    /** Primary identifier (typically the integer id). */
    public function getAuthIdentifier(): int|string;

    /** The hashed password to compare against on attempt(). */
    public function getAuthPasswordHash(): string;

    /** The attribute name holding the identifier — defaults to 'id'. */
    public function getAuthIdentifierName(): string;
}

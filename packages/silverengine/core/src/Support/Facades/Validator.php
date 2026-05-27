<?php

declare(strict_types=1);

namespace Silver\Support\Facades;

use Silver\Support\Facade;

/**
 * Static-style entry point for {@see \Silver\Http\Validator}. Resolves
 * the underlying instance through the container — tests can swap it
 * with `Container::instance(\Silver\Http\Validator::class, $fake)`.
 *
 * Usage stays identical to the old static class:
 *
 *     $result = Validator::check($data, $rules);
 *     if ($result->fails()) { ... }
 *
 * Behind the scenes every call goes through the same shared instance
 * but state lives on the returned {@see ValidationResult}, not on the
 * Validator itself — so back-to-back checks never interfere.
 */
final class Validator extends Facade
{
    protected static function getClass(): string
    {
        return \Silver\Http\Validator::class;
    }
}

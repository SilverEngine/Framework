<?php
declare(strict_types=1);

namespace Silver\Auth\Concerns;

use Silver\Auth\Access\Gate;
use Silver\Auth\Contracts\Authenticatable;

/**
 * Mix into an Authenticatable model for `$user->can('update', $post)`
 * / `cant()` sugar over the shared {@see Gate} singleton.
 *
 * @phpstan-require-implements Authenticatable
 */
trait Authorizable
{
    public function can(string $ability, mixed ...$args): bool
    {
        return app(Gate::class)->forUser($this)->allows($ability, ...$args);
    }

    public function cant(string $ability, mixed ...$args): bool
    {
        return !$this->can($ability, ...$args);
    }
}

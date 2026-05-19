<?php
declare(strict_types=1);

namespace Silver\Engine\Ghost;

use Closure;

/**
 * A deferred prop. Excluded from the initial page load but advertised in the
 * page object's `deferredProps` map so the Inertia client automatically
 * fetches it (and may prefetch it) right after mount.
 */
final class DeferProp
{
    public function __construct(
        private readonly Closure $callback,
        public readonly string $group = 'default',
    ) {
    }

    public function __invoke(): mixed
    {
        return ($this->callback)();
    }
}

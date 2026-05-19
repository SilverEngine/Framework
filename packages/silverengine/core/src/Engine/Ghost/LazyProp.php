<?php
declare(strict_types=1);

namespace Silver\Engine\Ghost;

use Closure;

/**
 * An optional ("lazy") prop. Never serialized on the initial page load;
 * resolved only when a partial reload explicitly requests its key.
 */
final class LazyProp
{
    public function __construct(private readonly Closure $callback)
    {
    }

    public function __invoke(): mixed
    {
        return ($this->callback)();
    }
}

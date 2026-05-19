<?php

declare(strict_types=1);

namespace Silver\Engine;

/**
 * Top-level `silver` CLI commands. Backed values are the canonical
 * tokens; `parse()` additionally accepts the legacy `c` alias for
 * `g` (generate), so dispatch behaviour is identical to the previous
 * `match ($this->cmd) { 'g','c' => …, 'd' => …, … }` with an unknown
 * command resolving to null (the old `default` arm).
 */
enum Command: string
{
    case Generate = 'g';
    case Delete   = 'd';
    case Migrate       = 'migrate';
    case Serve         = 'serve';
    case Optimize      = 'optimize';
    case OptimizeClear = 'optimize:clear';
    case Help          = 'help';

    public static function parse(string $cmd): ?self
    {
        return $cmd === 'c' ? self::Generate : self::tryFrom($cmd);
    }
}

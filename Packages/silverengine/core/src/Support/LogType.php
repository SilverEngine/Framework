<?php

declare(strict_types=1);

namespace Silver\Support;

/**
 * The log channels SilverEngine writes. Backed values match the method
 * names dispatched through {@see Log::__call()} (e.g. `Log::error('x')`),
 * so this enum is a drop-in for the old `private const array TYPES` with
 * no behaviour change — same accepted names, same on-disk `[ type ]` tag.
 */
enum LogType: string
{
    case Info    = 'info';
    case Ok      = 'ok';
    case Warning = 'warning';
    case Error   = 'error';
    case Api     = 'api';
    case Db      = 'db';
    case Start   = 'start';
    case End     = 'end';
    case Debug   = 'debug';
    case Normal  = 'normal';
    case Danger  = 'danger';
    case Aboard  = 'aboard';
    case Finish  = 'finish';
    case Url     = 'url';

    /**
     * Comma-separated list of every accepted channel name — used verbatim
     * in the "Allowed: ..." portion of the undefined-method exception so
     * the message text stays identical to the pre-enum implementation.
     */
    public static function names(): string
    {
        return implode(', ', array_map(static fn (self $t): string => $t->value, self::cases()));
    }
}

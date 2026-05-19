<?php
declare(strict_types=1);

namespace Silver\Helpers;

final class Str
{
    public static function endsWith(string $str, string $end): bool
    {
        return str_ends_with($str, $end);
    }

    public static function startsWith(string $str, string $begin): bool
    {
        return str_starts_with($str, $begin);
    }
}

<?php
declare(strict_types=1);

namespace Silver\Orm\Casts;

final class StringCast implements CastsAttribute
{
    public function get(mixed $value): mixed { return $value === null ? null : (string) $value; }
    public function set(mixed $value): mixed { return $value === null ? null : (string) $value; }
}

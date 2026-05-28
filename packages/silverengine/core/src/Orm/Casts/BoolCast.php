<?php
declare(strict_types=1);

namespace Silver\Orm\Casts;

final class BoolCast implements CastsAttribute
{
    public function get(mixed $value): mixed { return $value === null ? null : (bool) $value; }
    public function set(mixed $value): mixed { return $value === null ? null : ($value ? 1 : 0); }
}

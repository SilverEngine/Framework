<?php
declare(strict_types=1);

namespace Silver\Orm\Casts;

final class IntCast implements CastsAttribute
{
    public function get(mixed $value): mixed { return $value === null ? null : (int) $value; }
    public function set(mixed $value): mixed { return $value === null ? null : (int) $value; }
}

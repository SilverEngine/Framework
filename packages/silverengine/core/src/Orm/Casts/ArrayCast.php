<?php
declare(strict_types=1);

namespace Silver\Orm\Casts;

final class ArrayCast implements CastsAttribute
{
    public function get(mixed $value): mixed
    {
        if ($value === null || $value === '') return [];
        $decoded = json_decode((string) $value, associative: true, flags: JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    }
    public function set(mixed $value): mixed
    {
        return $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}

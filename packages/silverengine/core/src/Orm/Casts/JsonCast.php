<?php
declare(strict_types=1);

namespace Silver\Orm\Casts;

final class JsonCast implements CastsAttribute
{
    public function get(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        return json_decode((string) $value, associative: true, flags: JSON_THROW_ON_ERROR);
    }
    public function set(mixed $value): mixed
    {
        return $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}

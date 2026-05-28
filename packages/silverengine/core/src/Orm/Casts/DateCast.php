<?php
declare(strict_types=1);

namespace Silver\Orm\Casts;

use DateTimeImmutable;
use DateTimeZone;

final class DateCast implements CastsAttribute
{
    public function get(mixed $value): mixed
    {
        if ($value === null || $value === '') return null;
        if ($value instanceof DateTimeImmutable) return $value;
        return new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
    }
    public function set(mixed $value): mixed
    {
        if ($value === null) return null;
        if ($value instanceof DateTimeImmutable) {
            return $value->format('Y-m-d');
        }
        return (new DateTimeImmutable((string) $value, new DateTimeZone('UTC')))->format('Y-m-d');
    }
}

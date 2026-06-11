<?php
declare(strict_types=1);

namespace Silver\Orm\Casts;

use Silver\Orm\Collection;

/**
 * Casts a JSON text column to a {@see Collection} and back.
 * NULL stays NULL in both directions.
 */
final class AsCollection implements CastsAttribute
{
    public function get(mixed $value): ?Collection
    {
        if ($value === null) {
            return null;
        }
        $decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
        return new Collection(is_array($decoded) ? $decoded : [$decoded]);
    }

    public function set(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof Collection) {
            $value = $value->toArray();
        }
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}

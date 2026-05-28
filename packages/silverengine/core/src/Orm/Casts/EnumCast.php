<?php
declare(strict_types=1);

namespace Silver\Orm\Casts;

use BackedEnum;

final readonly class EnumCast implements CastsAttribute
{
    /** @param class-string<BackedEnum> $enumClass */
    public function __construct(private string $enumClass) {}
    public function get(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof BackedEnum) {
            return $value;
        }
        /** @var class-string<BackedEnum> $cls */
        $cls = $this->enumClass;
        return $cls::from($value);
    }
    public function set(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}

<?php
declare(strict_types=1);

namespace Silver\Orm\Model;

use Silver\Orm\Casts\CastsAttribute;

final readonly class PropertyMetadata
{
    public function __construct(
        public string          $name,
        public ?string         $type,
        public bool            $allowsNull,
        public ?CastsAttribute $cast,
        public bool            $hidden,
        public bool            $fillable,
        public bool            $guarded,
        public bool            $isPrimary,
    ) {}
}

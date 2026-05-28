<?php
declare(strict_types=1);

namespace Silver\Orm\Model;

use Silver\Orm\Concerns\SoftDeletes;
use Silver\Orm\Concerns\Timestamps;

final readonly class ModelMetadata
{
    /**
     * @param class-string                          $class
     * @param array<string, PropertyMetadata>       $properties
     * @param list<string>                          $hidden
     * @param list<string>                          $fillable
     * @param list<string>                          $guarded
     * @param list<class-string>                    $observers
     * @param array<string, string>                 $scopes        scope-method name → real method on the model
     * @param list<class-string<\Silver\Orm\Contracts\GlobalScopeInterface>> $globalScopes
     */
    public function __construct(
        public string       $class,
        public string       $table,
        public ?string      $connection,
        public ?string      $primaryKey,
        public bool         $primaryKeyIncrementing,
        public array        $properties,
        public array        $hidden,
        public array        $fillable,
        public array        $guarded,
        public ?Timestamps  $timestamps,
        public ?SoftDeletes $softDeletes,
        public array        $observers,
        public array        $scopes       = [],
        public array        $globalScopes = [],
    ) {}

    public function hasTimestamps(): bool { return $this->timestamps !== null; }
    public function hasSoftDeletes(): bool { return $this->softDeletes !== null; }
}

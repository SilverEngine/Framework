<?php
declare(strict_types=1);

namespace Silver\Orm\Attributes;

use Attribute;

/**
 * Override the table name inferred from the model class basename.
 *
 *   #[Table('app_users')]
 *   final class User extends Model {}
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Table
{
    public function __construct(
        public string $name,
        public ?string $connection = null,
    ) {}
}

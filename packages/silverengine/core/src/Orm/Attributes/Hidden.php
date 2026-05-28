<?php
declare(strict_types=1);

namespace Silver\Orm\Attributes;

use Attribute;

/**
 * Strip this property from ->toArray() / ->jsonSerialize() output.
 * Used for password, salt, recovery tokens, anything that shouldn't
 * leave the server.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Hidden
{
}

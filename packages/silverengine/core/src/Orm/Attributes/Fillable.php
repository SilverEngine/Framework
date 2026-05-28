<?php
declare(strict_types=1);

namespace Silver\Orm\Attributes;

use Attribute;

/**
 * Allow mass assignment for this property. Required because the ORM
 * is deny-by-default (the opposite of Eloquent) — tagging a property
 * is the only way to make Model::create([...]) populate it.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Fillable
{
}

<?php
declare(strict_types=1);

namespace Silver\Orm\Casts;

/**
 * Lets a domain value object declare its own cast: `#[Cast(Money::class)]`
 * on a property resolves to `Money::castUsing($args)` when Money
 * implements this interface — the value object stays free of any
 * CastsAttribute plumbing.
 */
interface Castable
{
    /** @param array<int, mixed> $args Extra args from the #[Cast] attribute. */
    public static function castUsing(array $args): CastsAttribute;
}

<?php
declare(strict_types=1);

namespace Silver\Orm\Attributes;

use Attribute;

/**
 * Explicit denial of mass assignment. Redundant against the
 * deny-by-default rule, but useful as a hard signal for sensitive
 * fields (password, totp_secret, …) so reviewers see the intent.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Guarded
{
}

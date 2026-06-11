<?php
declare(strict_types=1);

namespace Tests\Unit\Framework\Orm\Casts\fixtures;

use Silver\Orm\Casts\Castable;
use Silver\Orm\Casts\CastsAttribute;

/**
 * Domain value object that knows how to cast itself — stored as
 * "EUR 12.50"-style text in one column.
 */
final readonly class Money implements Castable
{
    public function __construct(
        public string $currency,
        public int    $cents,
    ) {}

    public static function castUsing(array $args): CastsAttribute
    {
        return new class implements CastsAttribute {
            public function get(mixed $value): mixed
            {
                if ($value === null) {
                    return null;
                }
                [$currency, $amount] = explode(' ', (string) $value, 2);
                return new Money($currency, (int) round((float) $amount * 100));
            }

            public function set(mixed $value): mixed
            {
                if ($value === null) {
                    return null;
                }
                if (!$value instanceof Money) {
                    throw new \InvalidArgumentException('Expected Money.');
                }
                return $value->currency . ' ' . number_format($value->cents / 100, 2, '.', '');
            }
        };
    }
}

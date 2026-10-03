<?php

declare(strict_types=1);

namespace App\Casts;

use Brick\Money\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Maps an integer `*_cents` column to a Brick\Money\Money value object.
 * Only Money instances in the column's currency can be written, so floats
 * and bare integers can never sneak into money columns.
 *
 * Usage: `'total_cents' => MoneyCast::class` (ZAR) or `MoneyCast::class.':USD'`.
 *
 * Writes accept anything so that wrong types fail loudly at runtime.
 *
 * @implements CastsAttributes<Money|null, mixed>
 */
final readonly class MoneyCast implements CastsAttributes
{
    public function __construct(private string $currency = 'ZAR') {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        return Money::ofMinor((int) $value, $this->currency);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof Money) {
            throw new InvalidArgumentException("[{$key}] must be set with a Brick\\Money\\Money instance, not ".get_debug_type($value).'.');
        }

        if ($value->getCurrency()->getCurrencyCode() !== $this->currency) {
            throw new InvalidArgumentException("[{$key}] must be in {$this->currency}, got {$value->getCurrency()->getCurrencyCode()}.");
        }

        return $value->getMinorAmount()->toInt();
    }
}

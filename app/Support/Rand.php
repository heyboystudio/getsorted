<?php

declare(strict_types=1);

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;

/** Rand amounts for people: "R 1 234.50" out, "1234.50" in. Integer cents only, no floats. */
final class Rand
{
    public static function format(int $cents): string
    {
        $amount = BigDecimal::ofUnscaledValue($cents, 2);
        [$whole, $fraction] = explode('.', (string) $amount->abs());
        $whole = ltrim(strrev(implode(' ', str_split(strrev($whole), 3))));

        return ($cents < 0 ? '-' : '').'R '.$whole.'.'.$fraction;
    }

    /**
     * "1234", "1234.5", "1 234.50", "1,234.50" or "120,50" → cents; null when it is not a plain rand amount.
     * A comma before one or two final digits is a decimal comma, never a thousands separator.
     */
    public static function toCents(string $input): ?int
    {
        $clean = str_replace([' ', "\u{00A0}", 'R', 'r'], '', trim($input));

        if (preg_match('/^\d+,\d{1,2}$/', $clean) === 1) {
            $clean = str_replace(',', '.', $clean);
        } elseif (str_contains($clean, ',')) {
            if (preg_match('/^\d{1,3}(,\d{3})+(\.\d{1,2})?$/', $clean) !== 1) {
                return null;
            }

            $clean = str_replace(',', '', $clean);
        }

        if (preg_match('/^\d{1,9}(\.\d{1,2})?$/', $clean) !== 1) {
            return null;
        }

        try {
            return BigDecimal::of($clean)->multipliedBy(100)->toInt();
        } catch (MathException) {
            return null;
        }
    }
}

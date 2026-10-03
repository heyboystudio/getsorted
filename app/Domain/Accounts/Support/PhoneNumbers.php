<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Support;

use libphonenumber\NumberParseException;
use Propaganistas\LaravelPhone\PhoneNumber;

/** South African mobile number handling: normalising, and masking for screens and logs. */
final class PhoneNumbers
{
    /** E.164 (e.g. +27821234567) for a valid SA mobile number, otherwise null. */
    public static function normaliseSaMobile(string $input): ?string
    {
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        try {
            $number = new PhoneNumber($input, 'ZA');

            if (! $number->isValid() || ! $number->isOfCountry('ZA') || ! $number->isOfType('mobile')) {
                return null;
            }

            return $number->formatE164();
        } catch (NumberParseException) {
            return null;
        }
    }

    /** For screens: +27821234567 → +27 82 *** 4567 */
    public static function maskForDisplay(string $phoneE164): string
    {
        if (! preg_match('/^\+27(\d{2})\d{3}(\d{4})$/', $phoneE164, $parts)) {
            return self::maskForLogs($phoneE164);
        }

        return "+27 {$parts[1]} *** {$parts[2]}";
    }

    /** For logs: +27821234567 → +278******67 (security baseline §6). */
    public static function maskForLogs(string $phoneE164): string
    {
        $length = mb_strlen($phoneE164);

        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return mb_substr($phoneE164, 0, 4).str_repeat('*', $length - 6).mb_substr($phoneE164, -2);
    }
}

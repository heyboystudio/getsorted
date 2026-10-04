<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Support;

use App\Domain\Assistant\Support\Redactor;

/**
 * Hides contact and bank details in quote text before the customer sees it
 * (docs/product/matching.md, anti-leakage; spec 010, AC6).
 */
final class ContactMasker
{
    /** A banking word followed by six or more digits, grouped by at most single spaces or dashes. */
    private const string BANK = '/\b(?:acc(?:ount)?|acc\s*no|a\/c|branch(?:\s*code)?|bank)\b[^\d\n]{0,20}(?:\d[\s\-]?){5,}\d/iu';

    /**
     * Ordinary text (e.g. "12 m²") comes back unchanged and unflagged.
     *
     * @return array{string, bool} masked text and whether anything was masked
     */
    public static function mask(string $text): array
    {
        $bankMasked = (string) preg_replace(self::BANK, '[bank details]', $text, -1, $bankHits);

        if ($bankHits === 0 && ! Redactor::containsPersonalData($text)) {
            return [trim($text), false];
        }

        return [Redactor::strip($bankMasked), true];
    }
}

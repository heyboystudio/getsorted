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
    private const string BANK = '/\b(?:acc(?:ount)?|acc\s*no|a\/c|branch(?:\s*code)?|bank)\b[^\d\n]{0,20}\d[\d\s\-]{4,}\d/iu';

    /** @return array{string, bool} masked text and whether anything was masked */
    public static function mask(string $text): array
    {
        $masked = (string) preg_replace(self::BANK, '[bank details]', $text);
        $masked = Redactor::strip($masked);

        return [$masked, $masked !== trim($text)];
    }
}

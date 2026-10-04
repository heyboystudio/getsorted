<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Support;

use Normalizer;

/**
 * Replaces contact details, street addresses, links and identity/card numbers
 * with placeholders before customer text reaches the AI provider, and finds
 * them in model output (security baseline §7, spec 007 AC10–AC11).
 */
final class Redactor
{
    private const string STREET_TYPES = 'road|rd|street|st|avenue|ave|drive|dr|lane|ln|crescent|cres|close|place|pl|way|terrace|boulevard|blvd|highway|hwy';

    public static function strip(string $text): string
    {
        $text = self::normalise($text);
        $streetTypes = self::STREET_TYPES;

        $patterns = [
            '/[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}.\-]+\.\p{L}{2,}/u' => '[email]',
            // Spelled-out emails: "andy at gmail dot com".
            '/\b[\p{L}\p{N}._%+\-]+\s+at\s+[\p{L}\p{N}\-]+(?:\s+dot\s+[\p{L}]{2,})+\b/iu' => '[email]',
            '/\b(?:https?:\/\/|www\.)\S+/iu' => '[link]',
            '/\b[\p{L}\p{N}\-]+\.(?:com|co\.za|za|net|org|io|me|info|biz|ly|link|app|xyz)(?:\/\S*)?\b/iu' => '[link]',
            // Street addresses: "14 Smith Rd", "7 Private Lane", "12a Florida Road".
            '/\b\d{1,5}[a-z]?\s+(?:[\p{L}\'\-]+\s+){1,3}(?:'.$streetTypes.')\b\.?/iu' => '[address]',
            // South African ID numbers: 13 digits (checked before cards and phones).
            '/\b\d{13}\b/u' => '[id number]',
            // Card numbers: 13–19 digits, optionally grouped by spaces or dashes.
            '/\b(?:\d[ \-]?){12,18}\d\b/u' => '[card number]',
            // Phone numbers: 9+ digits with optional + / 00 prefix and common separators (dates have only 8).
            '/(?:\+|00)?\d(?:[\s().\-\/_]{0,2}\d){8,}/u' => '[phone]',
        ];

        return trim((string) preg_replace(array_keys($patterns), array_values($patterns), $text));
    }

    /** True when text contains something strip() would remove. */
    public static function containsPersonalData(string $text): bool
    {
        return self::strip($text) !== trim(self::normalise($text));
    }

    /** Folds full-width and other compatibility characters and non-ASCII digits to plain ASCII digits. */
    private static function normalise(string $text): string
    {
        $text = Normalizer::normalize($text, Normalizer::FORM_KC) ?: $text;

        return (string) preg_replace_callback('/\p{Nd}/u', function (array $match): string {
            $digit = \IntlChar::charDigitValue(mb_ord($match[0]));

            return $digit >= 0 ? (string) $digit : $match[0];
        }, $text);
    }
}

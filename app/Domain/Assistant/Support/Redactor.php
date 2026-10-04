<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Support;

/**
 * Replaces contact details, links and identity/card numbers with placeholders
 * before customer text reaches the AI provider (security baseline §7, spec 007 AC10).
 */
final class Redactor
{
    /** @var array<string, string> pattern => placeholder, applied in order */
    private const array PATTERNS = [
        '/[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}.\-]+\.\p{L}{2,}/u' => '[email]',
        '/\b(?:https?:\/\/|www\.)\S+/iu' => '[link]',
        '/\b[\p{L}\p{N}\-]+\.(?:com|co\.za|za|net|org|io|me|info|biz|ly|link|app|xyz)(?:\/\S*)?\b/iu' => '[link]',
        // South African ID numbers: 13 digits (checked before cards and phones).
        '/\b\d{13}\b/' => '[id number]',
        // Card numbers: 13–19 digits, optionally grouped by spaces or dashes.
        '/\b(?:\d[ \-]?){12,18}\d\b/' => '[card number]',
        // Phone numbers: +27 / 0 prefixes and other 9+ digit runs with separators.
        '/(?:\+|00)?\d[\d\s().\-]{7,}\d/' => '[phone]',
    ];

    public static function strip(string $text): string
    {
        $text = (string) preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $text);

        return trim($text);
    }

    /** True when text contains something strip() would remove. */
    public static function containsPersonalData(string $text): bool
    {
        return self::strip($text) !== trim($text);
    }
}

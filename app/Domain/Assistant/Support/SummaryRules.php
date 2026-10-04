<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Support;

/** What an AI-written job description may contain before a customer or pro sees it (spec 007, AC11). */
final class SummaryRules
{
    public const int MAX_LENGTH = 600;

    /** Rand amounts ("R450", "R 1 200", "ZAR 300") and other currency amounts. */
    private const string PRICE = '/(?:\bR\s?\d|\bZAR\s?\d|\$\s?\d|€\s?\d|£\s?\d|\d\s?(?:rand|zar)\b)/iu';

    public static function acceptable(?string $summary): bool
    {
        if ($summary === null) {
            return false;
        }

        $summary = trim($summary);

        return $summary !== ''
            && mb_strlen($summary) <= self::MAX_LENGTH
            && preg_match(self::PRICE, $summary) !== 1
            && ! Redactor::containsPersonalData($summary);
    }
}

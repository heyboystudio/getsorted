<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Support;

/** Stable identifiers for trades: they link jobs and pros and never change. */
final class CatalogueKey
{
    public const string PATTERN = '/^[a-z][a-z0-9_]*$/';

    public static function isValid(string $key): bool
    {
        return preg_match(self::PATTERN, $key) === 1 && mb_strlen($key) <= 64;
    }
}

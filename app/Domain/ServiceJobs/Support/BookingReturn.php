<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Support;

/**
 * Where "add a property" sends the customer back to. Only GetSorted booking paths
 * are accepted, so the parameter can't be used as an open redirect.
 */
final class BookingReturn
{
    public static function sanitise(?string $path): ?string
    {
        if (! is_string($path) || ! preg_match('#^/(book(/[a-z0-9_]+)?|app/jobs/[0-9A-HJKMNP-TV-Z]{26}/continue)\z#i', $path)) {
            return null;
        }

        return $path;
    }
}

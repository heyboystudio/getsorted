<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Dates as Durban customers see them. Times are stored in UTC, but "today" and
 * booking days follow Africa/Johannesburg (conventions: store UTC, show SAST).
 */
final class LocalTime
{
    public static function timezone(): string
    {
        return (string) config('sortd.timezone');
    }

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone())->startOfDay();
    }
}

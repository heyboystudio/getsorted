<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Invite waves (spec 009; defaults in docs/product/matching.md). */
final class MatchingSettings extends Settings
{
    public int $wave_one_size;

    public int $later_wave_size;

    /** Hours after the latest wave before the next one, while quotes are short. */
    public int $wave_interval_hours;

    public int $invite_expiry_hours;

    /** Later waves stop once a job has this many quotes. */
    public int $enough_quotes;

    public static function group(): string
    {
        return 'matching';
    }
}

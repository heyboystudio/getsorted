<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Distance matching (spec 020): one invite round to the nearest eligible pros; the first quotes win. */
final class MatchingSettings extends Settings
{
    /** How many pros are invited when a job is posted. */
    public int $invite_count;

    /** Quotes a job accepts before it is full. */
    public int $max_quotes;

    /** A pro's service radius until they change it. */
    public int $default_radius_km;

    /** A pro just outside their radius is still eligible, only to fill the invites. */
    public int $soft_edge_km;

    public int $invite_expiry_hours;

    public static function group(): string
    {
        return 'matching';
    }
}

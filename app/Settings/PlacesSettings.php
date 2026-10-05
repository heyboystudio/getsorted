<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Address autocomplete limits (spec 015); super-admins change them in the admin panel. */
final class PlacesSettings extends Settings
{
    /** Address searches per Durban day before the forms fall back to manual entry. */
    public int $daily_session_cap;

    public static function group(): string
    {
        return 'places';
    }
}

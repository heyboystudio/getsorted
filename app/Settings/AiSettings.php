<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** AI scoping assistant switches and limits (spec 007); super-admins change them in the admin panel. */
final class AiSettings extends Settings
{
    /** Off until the privacy notice names the provider and its terms are accepted (founder decision 1). */
    public bool $enabled;

    /** Suggestions below this confidence fall back to manual choice. */
    public float $suggestion_min_confidence;

    /** Calls per Durban day before the assistant pauses until midnight (founder decision 2). */
    public int $daily_call_budget;

    public int $usage_retention_days;

    public static function group(): string
    {
        return 'ai';
    }
}

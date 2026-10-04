<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Pro vetting timers (spec 008, founder decisions 3 and 4). */
final class VettingSettings extends Settings
{
    /** Days a rejected applicant waits before reapplying. */
    public int $reapply_after_days;

    /** Months after the decision or last activity before a rejected or abandoned application's documents and references are deleted. */
    public int $retention_months;

    public static function group(): string
    {
        return 'vetting';
    }
}

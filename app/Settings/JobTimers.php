<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Job timers from docs/product/job-lifecycle.md; never hard-coded (conventions). */
final class JobTimers extends Settings
{
    /** Hours a posted job collects quotes (lifecycle default 72 h). */
    public int $quote_window_hours;

    /** Days before an unfinished draft is cancelled (spec 005, AC12). */
    public int $draft_expiry_days;

    public static function group(): string
    {
        return 'job_timers';
    }
}

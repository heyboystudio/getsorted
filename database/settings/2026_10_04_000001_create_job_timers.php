<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('job_timers.quote_window_hours', 72);
        $this->migrator->add('job_timers.draft_expiry_days', 7);
    }

    public function down(): void
    {
        $this->migrator->delete('job_timers.quote_window_hours');
        $this->migrator->delete('job_timers.draft_expiry_days');
    }
};

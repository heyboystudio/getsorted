<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Urgent jobs (urgent, or booked for today): a pro has 4 hours, not 24, to answer the invite (audit 2026-10-07). */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('matching.urgent_invite_expiry_hours', 4);
    }

    public function down(): void
    {
        $this->migrator->delete('matching.urgent_invite_expiry_hours');
    }
};

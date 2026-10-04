<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Spec 007: the assistant ships switched off (founder decision 1) with a 2,000 calls/day budget (decision 2). */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('ai.enabled', false);
        $this->migrator->add('ai.suggestion_min_confidence', 0.6);
        $this->migrator->add('ai.daily_call_budget', 2000);
        $this->migrator->add('ai.usage_retention_days', 90);
    }

    public function down(): void
    {
        $this->migrator->delete('ai.enabled');
        $this->migrator->delete('ai.suggestion_min_confidence');
        $this->migrator->delete('ai.daily_call_budget');
        $this->migrator->delete('ai.usage_retention_days');
    }
};

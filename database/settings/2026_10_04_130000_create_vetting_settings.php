<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Spec 008 founder decisions 3 and 4. */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('vetting.reapply_after_days', 90);
        $this->migrator->add('vetting.retention_months', 12);
    }

    public function down(): void
    {
        $this->migrator->delete('vetting.reapply_after_days');
        $this->migrator->delete('vetting.retention_months');
    }
};

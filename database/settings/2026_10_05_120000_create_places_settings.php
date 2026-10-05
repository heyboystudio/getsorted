<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Spec 015 AC10: address-search sessions per Durban day (founder approved 1,000). */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('places.daily_session_cap', 1000);
    }

    public function down(): void
    {
        $this->migrator->delete('places.daily_session_cap');
    }
};

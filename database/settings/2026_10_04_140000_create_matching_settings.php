<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Spec 009 defaults from docs/product/matching.md. */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('matching.wave_one_size', 5);
        $this->migrator->add('matching.later_wave_size', 3);
        $this->migrator->add('matching.wave_interval_hours', 12);
        $this->migrator->add('matching.invite_expiry_hours', 24);
        $this->migrator->add('matching.enough_quotes', 2);
    }

    public function down(): void
    {
        foreach (['wave_one_size', 'later_wave_size', 'wave_interval_hours', 'invite_expiry_hours', 'enough_quotes'] as $name) {
            $this->migrator->delete('matching.'.$name);
        }
    }
};

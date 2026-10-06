<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Spec 020: distance matching, one invite round of up to 10 pros, first 5 quotes accepted. */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('matching.invite_count', 10);
        $this->migrator->add('matching.max_quotes', 5);
        $this->migrator->add('matching.default_radius_km', 15);
        $this->migrator->add('matching.soft_edge_km', 2);

        foreach (['wave_one_size', 'later_wave_size', 'wave_interval_hours', 'enough_quotes'] as $name) {
            $this->migrator->delete('matching.'.$name);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Spec 020 replaced invite waves; this settings migration cannot be reversed.');
    }
};

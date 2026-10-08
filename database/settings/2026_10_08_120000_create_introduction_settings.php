<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Spec 023, decision 061: the introduction fee is R99, paid by the pro from prepaid credit, and switched off at launch. */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('introductions.fee_enabled', false);
        $this->migrator->add('introductions.fee_cents', 9_900);
        $this->migrator->add('introductions.free_introductions', 10);
        $this->migrator->add('introductions.credit_pack_cents', [29_700, 49_500, 99_000]);
    }

    public function down(): void
    {
        foreach (['fee_enabled', 'fee_cents', 'free_introductions', 'credit_pack_cents'] as $name) {
            $this->migrator->delete('introductions.'.$name);
        }
    }
};

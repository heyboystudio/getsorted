<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** Spec 010 defaults (money-flow.md; Q1/Q2 defaults; founder decisions 2 and 3). */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('quotes.max_deposit_percent', 50);
        $this->migrator->add('quotes.default_validity_days', 7);
        $this->migrator->add('quotes.max_total_cents', 50_000_000);
        $this->migrator->add('money.commission_percent', 12);
        $this->migrator->add('money.vat_percent', 15);
    }

    public function down(): void
    {
        foreach (['quotes.max_deposit_percent', 'quotes.default_validity_days', 'quotes.max_total_cents', 'money.commission_percent', 'money.vat_percent'] as $name) {
            $this->migrator->delete($name);
        }
    }
};

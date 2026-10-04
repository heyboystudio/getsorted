<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Commission and VAT rates (money-flow.md; open question Q1 default 12%; spec 010 decision 2). */
final class MoneySettings extends Settings
{
    /** Percentage of labour and call-out (not materials, Q2 default). */
    public int $commission_percent;

    /** Added to quotes of VAT-registered pros. */
    public int $vat_percent;

    public static function group(): string
    {
        return 'money';
    }
}

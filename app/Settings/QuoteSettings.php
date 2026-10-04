<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Quote limits (spec 010). */
final class QuoteSettings extends Settings
{
    public int $max_deposit_percent;

    public int $default_validity_days;

    public int $max_total_cents;

    public static function group(): string
    {
        return 'quotes';
    }
}

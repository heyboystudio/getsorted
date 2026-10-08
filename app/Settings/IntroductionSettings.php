<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** The fee a pro pays when a client chooses them (spec 023, decision 061). Off at launch. */
final class IntroductionSettings extends Settings
{
    /** PayFast refuses payments under R5.00 on every method, so no credit pack may be smaller. */
    public const int MIN_PACK_CENTS = 500;

    public bool $fee_enabled;

    /** Per introduction, in cents. */
    public int $fee_cents;

    /** Each pro's first introductions that cost nothing once the fee is on. */
    public int $free_introductions;

    /** @var list<int> credit pack sizes in cents a pro can buy */
    public array $credit_pack_cents;

    public static function group(): string
    {
        return 'introductions';
    }
}

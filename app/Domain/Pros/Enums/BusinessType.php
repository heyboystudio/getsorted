<?php

declare(strict_types=1);

namespace App\Domain\Pros\Enums;

enum BusinessType: string
{
    case SoleTrader = 'sole_trader';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::SoleTrader => __('Sole trader'),
            self::Company => __('Company'),
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Properties\Enums;

/** eThekwini launch regions (docs/product/launch-area.md). */
enum Region: string
{
    case BereaCentral = 'berea_central';
    case North = 'north';
    case West = 'west';
    case South = 'south';

    public function label(): string
    {
        return match ($this) {
            self::BereaCentral => __('Berea / central'),
            self::North => __('North'),
            self::West => __('West'),
            self::South => __('South'),
        };
    }
}

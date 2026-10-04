<?php

declare(strict_types=1);

namespace App\Domain\Properties\Enums;

enum PropertyType: string
{
    case House = 'house';
    case Flat = 'flat';
    case Townhouse = 'townhouse';
    case Business = 'business';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::House => __('House'),
            self::Flat => __('Flat / apartment'),
            self::Townhouse => __('Townhouse / complex'),
            self::Business => __('Business premises'),
            self::Other => __('Other'),
        };
    }
}

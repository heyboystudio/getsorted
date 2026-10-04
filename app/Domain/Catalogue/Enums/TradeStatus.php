<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Enums;

enum TradeStatus: string
{
    case Demo = 'demo';
    case Live = 'live';

    public function label(): string
    {
        return match ($this) {
            self::Demo => __('Demo'),
            self::Live => __('Live'),
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Catalogue\Enums;

enum TradeStatus: string
{
    case Demo = 'demo';
    case Live = 'live';
}

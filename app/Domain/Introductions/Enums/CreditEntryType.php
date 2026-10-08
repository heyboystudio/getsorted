<?php

declare(strict_types=1);

namespace App\Domain\Introductions\Enums;

enum CreditEntryType: string
{
    case Purchase = 'purchase';
    case Introduction = 'introduction';
    case Adjustment = 'adjustment';
}

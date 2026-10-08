<?php

declare(strict_types=1);

namespace App\Domain\Introductions\Enums;

enum CreditPurchaseStatus: string
{
    case Pending = 'pending';
    case Complete = 'complete';
    case Failed = 'failed';
}

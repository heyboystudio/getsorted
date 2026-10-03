<?php

declare(strict_types=1);

namespace App\Contracts\Data;

enum PaymentEventType: string
{
    case PaymentSucceeded = 'payment_succeeded';
    case PaymentFailed = 'payment_failed';
    case RefundSucceeded = 'refund_succeeded';
    case PayoutPaid = 'payout_paid';
    case PayoutFailed = 'payout_failed';
}

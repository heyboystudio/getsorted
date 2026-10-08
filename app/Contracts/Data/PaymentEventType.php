<?php

declare(strict_types=1);

namespace App\Contracts\Data;

enum PaymentEventType: string
{
    case PaymentSucceeded = 'payment_succeeded';
    case PaymentFailed = 'payment_failed';

    /** The provider has the payment but it is not settled yet (PayFast "PENDING"). */
    case PaymentPending = 'payment_pending';
    case RefundSucceeded = 'refund_succeeded';
    case PayoutPaid = 'payout_paid';
    case PayoutFailed = 'payout_failed';
}

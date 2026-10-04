<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Enums;

/** Job states (docs/product/job-lifecycle.md). Only ServiceJobStateMachine changes them. */
enum ServiceJobStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case AwaitingDeposit = 'awaiting_deposit';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case AwaitingFinalPayment = 'awaiting_final_payment';
    case Completed = 'completed';
    case Closed = 'closed';
    case Disputed = 'disputed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /** What the customer sees (lifecycle "Customer sees" column, simplified). */
    public function customerLabel(): string
    {
        return match ($this) {
            self::Draft => __('Finish your request'),
            self::Open => __('Finding your pros'),
            self::AwaitingDeposit => __('Pay deposit to confirm'),
            self::Scheduled => __('Booked'),
            self::InProgress => __('Work in progress'),
            self::AwaitingFinalPayment => __('Check the work, then pay'),
            self::Completed => __('Done'),
            self::Closed => __('Closed'),
            self::Disputed => __("We're looking into it"),
            self::Cancelled => __('Cancelled'),
            self::Expired => __('No quote accepted'),
        };
    }
}

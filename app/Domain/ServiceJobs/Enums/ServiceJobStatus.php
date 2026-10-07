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

    /** Filament badge colour for admins: grey = idle or ended, blue = moving, amber = waiting on money, green = done, red = needs attention. */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Open, self::Scheduled, self::InProgress => 'info',
            self::AwaitingDeposit, self::AwaitingFinalPayment => 'warning',
            self::Completed => 'success',
            self::Disputed => 'danger',
            self::Draft, self::Closed, self::Cancelled, self::Expired => 'gray',
        };
    }

    /** Jobs still going on, drafts included (customer Jobs tab "Active", spec 021). */
    /** @return list<self> */
    public static function inFlight(): array
    {
        return [self::Draft, self::Open, self::AwaitingDeposit, self::Scheduled, self::InProgress, self::AwaitingFinalPayment, self::Disputed];
    }

    /** Jobs that were posted and are underway, so they get a card on the customer's home. */
    /** @return list<self> */
    public static function underway(): array
    {
        return [self::Open, self::AwaitingDeposit, self::Scheduled, self::InProgress, self::AwaitingFinalPayment, self::Disputed];
    }

    /** @return list<self> */
    public static function finished(): array
    {
        return [self::Completed, self::Closed];
    }

    /** @return list<self> */
    public static function ended(): array
    {
        return [self::Cancelled, self::Expired];
    }
}

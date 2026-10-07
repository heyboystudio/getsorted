<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Enums;

/** A final-amount proposal (spec 018, AC9–AC13). */
enum ProposalStatus: string
{
    /** An increase waiting for the customer. */
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    /** A decrease: applies straight away (decision 2). */
    case Applied = 'applied';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting for the customer'),
            self::Approved => __('Approved'),
            self::Declined => __('Declined'),
            self::Withdrawn => __('Withdrawn'),
            self::Applied => __('Lowered'),
        };
    }

    /** Whether the proposal's total became the agreed final amount. */
    public function agreed(): bool
    {
        return in_array($this, [self::Approved, self::Applied], true);
    }
}

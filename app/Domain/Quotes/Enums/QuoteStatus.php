<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Enums;

/** docs/product/job-lifecycle.md → Quote (spec 010). */
enum QuoteStatus: string
{
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => __('Sent'),
            self::Accepted => __('Accepted'),
            self::Declined => __('Not chosen'),
            self::Withdrawn => __('Withdrawn'),
            self::Expired => __('Expired'),
            self::Superseded => __('Replaced by a newer version'),
        };
    }
}

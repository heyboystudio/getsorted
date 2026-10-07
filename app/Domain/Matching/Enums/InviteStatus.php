<?php

declare(strict_types=1);

namespace App\Domain\Matching\Enums;

/** A pro's invite to quote on a job (spec 009; docs/product/job-lifecycle.md). */
enum InviteStatus: string
{
    case Invited = 'invited';
    case Viewed = 'viewed';
    /** The pro said they can do the job; only now can they send an estimate. */
    case Accepted = 'accepted';
    /** Set by the quote builder (spec 010). */
    case Quoted = 'quoted';
    case Declined = 'declined';
    case Expired = 'expired';
    /** The job stopped collecting quotes. */
    case Closed = 'closed';

    /** Still waiting for the pro. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Invited, self::Viewed, self::Accepted], true);
    }

    /** @return list<self> */
    public static function open(): array
    {
        return [self::Invited, self::Viewed, self::Accepted];
    }

    public function label(): string
    {
        return match ($this) {
            self::Invited => __('Invited'),
            self::Viewed => __('Seen'),
            self::Accepted => __('Accepted'),
            self::Quoted => __('Quoted'),
            self::Declined => __('Declined'),
            self::Expired => __('Expired'),
            self::Closed => __('Closed'),
        };
    }
}

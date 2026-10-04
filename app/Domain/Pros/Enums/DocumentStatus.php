<?php

declare(strict_types=1);

namespace App\Domain\Pros\Enums;

enum DocumentStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Flagged = 'flagged';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Received'),
            self::Verified => __('Verified'),
            self::Flagged => __('Needs attention'),
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Pros\Enums;

/** Where a pro's request to change their services or registrations stands (spec 021, AC27). */
enum ProChangeStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting for review'),
            self::Approved => __('Approved'),
            self::Rejected => __('Not approved'),
        };
    }
}

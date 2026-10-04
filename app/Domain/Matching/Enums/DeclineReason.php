<?php

declare(strict_types=1);

namespace App\Domain\Matching\Enums;

enum DeclineReason: string
{
    case TooFar = 'too_far';
    case TooBusy = 'too_busy';
    case NotMyWork = 'not_my_work';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TooFar => __('Too far'),
            self::TooBusy => __('Too busy'),
            self::NotMyWork => __('Not my kind of work'),
            self::Other => __('Other'),
        };
    }
}

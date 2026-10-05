<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Enums;

/** Why someone reported a chat message (spec 018, AC6). */
enum MessageReportReason: string
{
    case OffPlatform = 'off_platform';
    case Abusive = 'abusive';
    case Spam = 'spam';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OffPlatform => __('Asks to pay or talk outside Sortd'),
            self::Abusive => __('Rude or abusive'),
            self::Spam => __('Spam'),
            self::Other => __('Something else'),
        };
    }
}

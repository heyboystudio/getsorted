<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Enums;

/** Preferred time windows (spec 005, decision 2). */
enum TimeWindow: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case Flexible = 'flexible';
    case Today = 'today';

    public function label(): string
    {
        return match ($this) {
            self::Morning => __('Morning (07:00–12:00)'),
            self::Afternoon => __('Afternoon (12:00–17:00)'),
            self::Flexible => __('Flexible'),
            self::Today => __('Urgent — today'),
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Enums;

/** Where a job's description for pros came from (spec 007, AC8). */
enum SummarySource: string
{
    case Ai = 'ai';
    case CustomerEdited = 'customer_edited';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Ai => __('Written with AI help'),
            self::CustomerEdited => __('Edited by the customer'),
            self::None => __('No description'),
        };
    }
}

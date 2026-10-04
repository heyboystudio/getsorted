<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Support;

use App\Domain\Matching\Enums\InviteStatus;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Models\ServiceJob;

/** Shared bookkeeping for quote actions; callers hold the job lock. */
final class QuoteFlow
{
    /** The hard cap on quotes per job (AC4). The admin's matching.enough_quotes only stops new waves earlier. */
    public const int MAX_QUOTES = 3;

    /** Recount the job's current quotes; once full, close every other open invite (AC4). */
    public static function refreshCount(ServiceJob $job): void
    {
        $count = $job->quotes()->where('status', QuoteStatus::Submitted)->count();
        $job->forceFill(['quotes_count' => $count])->save();

        if ($count >= self::MAX_QUOTES) {
            $job->invites()->whereIn('status', InviteStatus::open())
                ->update(['status' => InviteStatus::Closed->value, 'responded_at' => now(), 'updated_at' => now()]);
        }
    }
}

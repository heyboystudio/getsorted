<?php

declare(strict_types=1);

namespace App\Domain\Introductions\Support;

use App\Models\Pro;
use App\Models\ServiceJob;

/**
 * What a client may call a pro (spec 023, AC1): before the client chooses them it is their first name only,
 * so a client cannot look the business up and skip the introduction. The business name unlocks with it.
 */
final class ProIdentity
{
    public static function beforeIntroduction(Pro $pro): string
    {
        $pro->loadMissing('user');

        return trim((string) $pro->user->first_name) !== '' ? (string) $pro->user->first_name : (string) __('Pro');
    }

    /** The name the client of this job sees for this pro right now. */
    public static function displayName(Pro $pro, ServiceJob $job): string
    {
        $introduced = $job->accepted_quote_id !== null
            && $job->quotes()->whereKey($job->accepted_quote_id)->where('pro_id', $pro->id)->exists();

        return $introduced && $pro->business_name !== null ? $pro->business_name : self::beforeIntroduction($pro);
    }
}

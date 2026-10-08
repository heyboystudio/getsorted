<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Support;

use App\Domain\ServiceJobs\Enums\ActorType;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Models\Pro;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\User;

/** Who is who on a booked job (spec 024): the client, and the pro whose estimate was chosen. */
final class BookedJob
{
    /** Statuses in which a booked job can still be finished or cancelled. */
    public const array OPEN_STATUSES = [ServiceJobStatus::Scheduled, ServiceJobStatus::InProgress];

    public static function pro(ServiceJob $job): ?Pro
    {
        if ($job->accepted_quote_id === null) {
            return null;
        }

        $proId = Quote::query()->whereKey($job->accepted_quote_id)->value('pro_id');

        return $proId === null ? null : Pro::query()->with('user')->find($proId);
    }

    /** The side of the booking this user is on, or null if they are neither the client nor the chosen pro. */
    public static function sideOf(User $user, ServiceJob $job): ?ActorType
    {
        if ($job->customer_id === $user->id) {
            return ActorType::Customer;
        }

        return self::pro($job)?->user_id === $user->id ? ActorType::Pro : null;
    }

    public static function isOpen(ServiceJob $job): bool
    {
        return in_array($job->status, self::OPEN_STATUSES, true);
    }
}

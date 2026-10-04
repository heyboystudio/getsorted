<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Actions;

use App\Domain\ServiceJobs\Enums\ActorType;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\ServiceJobStateMachine;
use App\Models\ServiceJob;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class CancelServiceJob
{
    public function __construct(
        private ServiceJobStateMachine $stateMachine,
    ) {}

    /**
     * Cancels a draft (spec 005: abandoned drafts expire, or the customer removes it). Re-reads the job
     * under lock and does nothing if it is no longer a draft, so repeats are safe.
     */
    public function handle(ServiceJob $job, ActorType $actor, ?int $actorId, string $reason, ?CarbonImmutable $onlyIfUntouchedSince = null): ?ServiceJob
    {
        return DB::transaction(function () use ($job, $actor, $actorId, $reason, $onlyIfUntouchedSince): ?ServiceJob {
            $job = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);

            // The customer may have resumed the draft since it was picked for expiry.
            if ($job->status !== ServiceJobStatus::Draft || ($onlyIfUntouchedSince instanceof CarbonImmutable && $job->updated_at->isAfter($onlyIfUntouchedSince))) {
                return null;
            }

            $job->cancelled_at = now();
            $job->cancel_reason = $reason;

            $this->stateMachine->transition($job, ServiceJobStatus::Cancelled, 'draft_cancelled', $actor, $actorId, ['reason' => $reason]);

            return $job;
        });
    }
}

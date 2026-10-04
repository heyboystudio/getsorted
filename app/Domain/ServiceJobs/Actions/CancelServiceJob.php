<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Actions;

use App\Domain\ServiceJobs\Enums\ActorType;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\ServiceJobStateMachine;
use App\Models\ServiceJob;
use Illuminate\Support\Facades\DB;

final readonly class CancelServiceJob
{
    public function __construct(
        private ServiceJobStateMachine $stateMachine,
    ) {}

    /**
     * Cancels a draft (spec 005: abandoned drafts expire). Re-reads the job
     * under lock and does nothing if it is no longer a draft, so repeats are safe.
     */
    public function handle(ServiceJob $job, ActorType $actor, ?int $actorId, string $reason): ?ServiceJob
    {
        return DB::transaction(function () use ($job, $actor, $actorId, $reason): ?ServiceJob {
            $job = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);

            if ($job->status !== ServiceJobStatus::Draft) {
                return null;
            }

            $job->cancelled_at = now();
            $job->cancel_reason = $reason;

            $this->stateMachine->transition($job, ServiceJobStatus::Cancelled, 'draft_cancelled', $actor, $actorId, ['reason' => $reason]);

            return $job;
        });
    }
}

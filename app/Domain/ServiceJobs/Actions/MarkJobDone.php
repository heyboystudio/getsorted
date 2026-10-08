<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Actions;

use App\Domain\Notifications\Notify;
use App\Domain\ServiceJobs\Enums\ActorType;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Exceptions\CannotFinishJob;
use App\Domain\ServiceJobs\ServiceJobStateMachine;
use App\Domain\ServiceJobs\Support\BookedJob;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The client or the chosen pro says the work is done (spec 024, AC1–AC3). Nothing is paid through
 * GetSorted in the MVP, so "done" is just that: the job closes out and the other side is told.
 */
final readonly class MarkJobDone
{
    public function __construct(private ServiceJobStateMachine $stateMachine) {}

    public function handle(User $user, ServiceJob $job): ServiceJob
    {
        $job = DB::transaction(function () use ($user, $job): ServiceJob {
            $job = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);
            $side = BookedJob::sideOf($user, $job);

            if ($side === null) {
                throw new CannotFinishJob(__('This job is not yours to finish.'));
            }

            if (! BookedJob::isOpen($job)) {
                throw new CannotFinishJob(__('This job is not booked, or it is already finished.'));
            }

            $job->forceFill(['completed_at' => now(), 'completed_by' => $side->value]);
            $this->stateMachine->transition($job, ServiceJobStatus::Completed, 'job_done', $side, $user->id, ['by' => $side->value]);
            activity()->causedBy($user)->performedOn($job)->withProperties(['by' => $side->value])->log('job_done');

            return $job;
        });

        $this->tellTheOtherSide($job, $user);

        return $job;
    }

    private function tellTheOtherSide(ServiceJob $job, User $actor): void
    {
        $trade = mb_strtolower($job->trade->name);
        $pro = BookedJob::pro($job);

        if ($job->completed_by === ActorType::Customer->value && $pro !== null) {
            Notify::user($pro->user, 'job_done', __('Your :trade job is marked done', ['trade' => $trade]), __('The client says the work is done. Thank you!'), route('pros.jobs'));

            return;
        }

        if ($job->completed_by === ActorType::Pro->value) {
            Notify::user($job->customer, 'job_done', __('Your :trade job is marked done', ['trade' => $trade]), __('Your pro says the work is done. Open the job if anything is not right.'), route('jobs.show', $job), email: true);
        }
    }
}

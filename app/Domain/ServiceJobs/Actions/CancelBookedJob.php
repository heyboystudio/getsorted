<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Actions;

use App\Domain\Notifications\Notify;
use App\Domain\ServiceJobs\Enums\ActorType;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Exceptions\CannotCancelJob;
use App\Domain\ServiceJobs\ServiceJobStateMachine;
use App\Domain\ServiceJobs\Support\BookedJob;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * After the introduction, either side can cancel with a reason (spec 024, AC4–AC7). The other side is
 * told. Introduction fees are not refunded automatically: support looks at it case by case.
 */
final readonly class CancelBookedJob
{
    public function __construct(private ServiceJobStateMachine $stateMachine) {}

    public function handle(User $user, ServiceJob $job, string $reason): ServiceJob
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 300) {
            throw new CannotCancelJob(__('Tell us why in a few words (3 to 300 characters).'));
        }

        $job = DB::transaction(function () use ($user, $job, $reason): ServiceJob {
            $job = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);
            $side = BookedJob::sideOf($user, $job);

            if ($side === null) {
                throw new CannotCancelJob(__('This job is not yours to cancel.'));
            }

            if (! BookedJob::isOpen($job)) {
                throw new CannotCancelJob(__('This job can no longer be cancelled here. Please contact support.'));
            }

            $job->forceFill(['cancelled_at' => now(), 'cancelled_by' => $side->value, 'cancel_reason' => $reason]);
            $this->stateMachine->transition($job, ServiceJobStatus::Cancelled, 'booking_cancelled_by_'.$side->value, $side, $user->id, ['reason' => $reason]);
            activity()->causedBy($user)->performedOn($job)->withProperties(['by' => $side->value, 'reason' => $reason])->log('booking_cancelled');

            return $job;
        });

        $this->tellTheOtherSide($job);

        return $job;
    }

    private function tellTheOtherSide(ServiceJob $job): void
    {
        $trade = mb_strtolower($job->trade->name);
        $pro = BookedJob::pro($job);

        if ($job->cancelled_by === ActorType::Customer->value && $pro !== null) {
            Notify::user($pro->user, 'booking_cancelled', __('A :trade job was cancelled', ['trade' => $trade]), __('The client cancelled this booking. Reason: :reason', ['reason' => $job->cancel_reason]), route('pros.jobs'), email: true);

            return;
        }

        if ($job->cancelled_by === ActorType::Pro->value) {
            Notify::user($job->customer, 'booking_cancelled', __('Your pro cancelled your :trade job', ['trade' => $trade]), __('Reason: :reason. You can post the job again and choose someone else.', ['reason' => $job->cancel_reason]), route('book.trade', $job->trade), email: true);
        }
    }
}

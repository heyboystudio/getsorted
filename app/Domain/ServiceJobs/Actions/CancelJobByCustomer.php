<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Actions;

use App\Domain\Matching\Enums\InviteStatus;
use App\Domain\Notifications\Notify;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Domain\ServiceJobs\Enums\ActorType;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Exceptions\CannotCancelJob;
use App\Domain\ServiceJobs\ServiceJobStateMachine;
use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A client cancels an open job before any quote is accepted. Invites are closed, quotes declined and
 * the pros who were invited are told. After acceptance the client has to contact support.
 */
final readonly class CancelJobByCustomer
{
    public function __construct(
        private ServiceJobStateMachine $stateMachine,
    ) {}

    public function handle(User $customer, ServiceJob $job, ?string $reason = null): ServiceJob
    {
        $invited = [];

        $job = DB::transaction(function () use ($customer, $job, $reason, &$invited): ServiceJob {
            $job = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);

            if ($job->customer_id !== $customer->id) {
                throw new CannotCancelJob(__('This job is not yours to cancel.'));
            }

            if ($job->status !== ServiceJobStatus::Open || $job->accepted_quote_id !== null) {
                throw new CannotCancelJob(__('This job can no longer be cancelled here. Please contact support.'));
            }

            $job->quotes()->where('status', QuoteStatus::Submitted)->update(['status' => QuoteStatus::Declined->value, 'updated_at' => now()]);
            $invited = $job->invites()->whereIn('status', [...InviteStatus::open(), InviteStatus::Quoted])->with('pro.user')->get()->all();
            $job->invites()->whereIn('status', InviteStatus::open())->update(['status' => InviteStatus::Closed->value, 'responded_at' => now(), 'updated_at' => now()]);

            $job->cancelled_at = now();
            $job->cancel_reason = $reason !== null && trim($reason) !== '' ? trim($reason) : 'Cancelled by client';
            $job->quotes_count = 0;

            $this->stateMachine->transition($job, ServiceJobStatus::Cancelled, 'job_cancelled_by_customer', ActorType::Customer, $customer->id, ['reason' => $job->cancel_reason]);

            return $job;
        });

        $trade = mb_strtolower($job->trade->name);

        /** @var ServiceJobInvite $invite */
        foreach ($invited as $invite) {
            Notify::user($invite->pro->user, 'job_cancelled', __('A :trade job was cancelled', ['trade' => $trade]), __('The client cancelled this job, so you no longer need to quote.'), route('pros.jobs'));
        }

        $job->clearMediaCollection(ServiceJob::PHOTO_COLLECTION);

        return $job;
    }
}

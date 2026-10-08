<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Actions;

use App\Domain\Introductions\Actions\RecordIntroduction;
use App\Domain\Matching\Enums\InviteStatus;
use App\Domain\Pros\Enums\ProStatus;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Domain\Quotes\Exceptions\CannotQuote;
use App\Domain\ServiceJobs\Enums\ActorType;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\ServiceJobStateMachine;
use App\Jobs\SendQuoteMessage;
use App\Models\Pro;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The customer accepts one quote (spec 010, AC8, AC10), which is the introduction (spec 023): the job is booked
 * (deposits are agreed directly with the pro, decision 058), the other quotes are declined and the
 * remaining invites close, all under the job lock.
 */
final readonly class AcceptQuote
{
    public function __construct(private ServiceJobStateMachine $stateMachine, private RecordIntroduction $recordIntroduction) {}

    public function handle(User $customer, Quote $quote): ServiceJob
    {
        Gate::forUser($customer)->authorize('accept', $quote);

        [$job, $declined] = DB::transaction(function () use ($customer, $quote): array {
            $job = ServiceJob::query()->lockForUpdate()->findOrFail($quote->service_job_id);
            $chosen = Quote::query()->lockForUpdate()->findOrFail($quote->id);

            if ($job->status !== ServiceJobStatus::Open) {
                throw new CannotQuote(__('This job already has an accepted quote or is closed.'));
            }

            if ($chosen->status !== QuoteStatus::Submitted || $chosen->isPastValidity()) {
                throw new CannotQuote(__('This quote can no longer be accepted.'));
            }

            if (Pro::query()->findOrFail($chosen->pro_id)->status !== ProStatus::Approved) {
                throw new CannotQuote(__('This pro is unavailable right now.'));
            }

            $chosen->forceFill(['status' => QuoteStatus::Accepted, 'accepted_at' => now()])->save();

            $declined = $job->quotes()->where('status', QuoteStatus::Submitted)->whereKeyNot($chosen->id)->pluck('id')->all();
            $job->quotes()->whereKey($declined)->update(['status' => QuoteStatus::Declined->value, 'updated_at' => now()]);
            $job->invites()->whereIn('status', InviteStatus::open())->update(['status' => InviteStatus::Closed->value, 'responded_at' => now(), 'updated_at' => now()]);

            $job->forceFill(['accepted_quote_id' => $chosen->id, 'scheduled_for' => $chosen->earliest_start_date->toDateString(), 'quotes_count' => 0]);
            $this->stateMachine->transition(
                $job,
                ServiceJobStatus::Scheduled,
                'quote_accepted',
                ActorType::Customer,
                $customer->id,
                ['quote' => $chosen->public_id, 'total_cents' => $chosen->total_cents, 'deposit_cents' => $chosen->deposit_cents],
            );

            $this->recordIntroduction->handle($job, $chosen, $customer);
            DB::table('pro_job_allocations')->insertOrIgnore(['pro_id' => $chosen->pro_id, 'service_job_id' => $job->id, 'allocated_at' => now()]);
            activity()->causedBy($customer)->performedOn($job)->withProperties(['quote' => $chosen->public_id])->log('quote_accepted');

            return [$job, $declined];
        });

        SendQuoteMessage::dispatch($quote->id, 'quote_accepted');

        foreach ($declined as $declinedId) {
            SendQuoteMessage::dispatch($declinedId, 'quote_not_chosen');
        }

        return $job;
    }
}

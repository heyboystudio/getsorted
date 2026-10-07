<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Actions;

use App\Domain\Quotes\Enums\ProposalStatus;
use App\Domain\Quotes\Exceptions\CannotQuote;
use App\Domain\ServiceJobs\Enums\ActorType;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\ServiceJobStateMachine;
use App\Jobs\SendFinalAmountMessage;
use App\Models\FinalAmountProposal;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The accepted pro cancels because the customer declined their price (spec 018,
 * AC12, decision 4). Only before work starts; the customer's deposit is owed back
 * in full, recorded on the event for the refunds spec to pay out.
 */
final readonly class CancelOverPrice
{
    public function __construct(private ServiceJobStateMachine $stateMachine) {}

    public function handle(User $user, ServiceJob $job): ServiceJob
    {
        $quote = $job->accepted_quote_id === null ? null : Quote::query()->with('pro')->find($job->accepted_quote_id);
        abort_unless($quote instanceof Quote && $quote->pro->user_id === $user->id, 404);

        [$cancelled, $proposal] = DB::transaction(function () use ($user, $job, $quote): array {
            $locked = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);

            if ($locked->status !== ServiceJobStatus::Scheduled) {
                throw new CannotQuote(__('Once work has started, contact Sortd support to sort out the price.'));
            }

            $latest = $locked->finalAmountProposals()->latest('version')->first();

            if (! $latest instanceof FinalAmountProposal || $latest->status !== ProposalStatus::Declined) {
                throw new CannotQuote(__('You can cancel over price only after the customer declines your proposed amount.'));
            }

            $locked->cancelled_at = now();
            $locked->cancel_reason = 'price_not_agreed';
            $this->stateMachine->transition($locked, ServiceJobStatus::Cancelled, 'cancelled_price_not_agreed', ActorType::Pro, $user->id, [
                'proposal' => $latest->public_id,
                // Decision 4: a full refund of any deposit paid (refunds spec).
                'refund_deposit_cents' => $quote->deposit_cents,
            ]);

            return [$locked, $latest];
        });

        SendFinalAmountMessage::dispatch($proposal->id, 'cancelled_price_not_agreed');

        return $cancelled;
    }
}

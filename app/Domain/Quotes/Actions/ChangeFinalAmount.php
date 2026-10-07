<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Actions;

use App\Domain\Pros\Enums\ProStatus;
use App\Domain\Quotes\Data\QuoteDraft;
use App\Domain\Quotes\Data\QuoteLineData;
use App\Domain\Quotes\Enums\ProposalStatus;
use App\Domain\Quotes\Exceptions\CannotQuote;
use App\Domain\Quotes\Support\QuoteCalculator;
use App\Domain\Quotes\Support\QuoteRules;
use App\Domain\ServiceJobs\Enums\ActorType;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\ServiceJobStateMachine;
use App\Jobs\SendFinalAmountMessage;
use App\Models\FinalAmountProposal;
use App\Models\Pro;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The accepted pro changes the final amount after seeing the job (spec 018, AC9–AC13).
 * Lower amounts apply at once (decision 2); higher ones wait for the customer, who
 * approves or declines. After one declined increase the pro gets one more try (decision 3).
 * Everything runs with the job locked; totals are always recalculated here.
 */
final readonly class ChangeFinalAmount
{
    /** Jobs whose price can still change: booked, before the final invoice. */
    public const array OPEN_STATUSES = [ServiceJobStatus::Scheduled, ServiceJobStatus::InProgress];

    /** Increases a customer may decline before the pro has to accept the agreed amount (decision 3). */
    private const int MAX_DECLINED = 2;

    public function __construct(
        private QuoteCalculator $calculator,
        private QuoteRules $rules,
        private ServiceJobStateMachine $stateMachine,
    ) {}

    /**
     * @param  list<QuoteLineData>  $lines
     */
    public function propose(User $user, ServiceJob $job, array $lines, string $reason): FinalAmountProposal
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('Explain the change in 10 to 500 characters.')]);
        }

        // Same line rules as an estimate; start date and validity don't apply here.
        $draft = new QuoteDraft($lines, 0, LocalTime::today(), 7, null);
        $pro = $this->acceptedPro($user, $job);
        $totals = $this->calculator->calculate($draft, $pro->isVatRegistered());
        $this->rules->check($draft, $totals);

        [$proposal, $template] = DB::transaction(function () use ($user, $job, $pro, $lines, $totals, $reason): array {
            $locked = $this->lockOpenJob($job);
            $previous = $this->agreedCents($locked);

            if ($totals->totalCents === $previous) {
                throw ValidationException::withMessages(['total' => __('That’s the same as the agreed amount.')]);
            }

            $increase = $totals->totalCents > $previous;
            $declined = $locked->finalAmountProposals()->where('status', ProposalStatus::Declined)->count();

            if ($increase && $declined >= self::MAX_DECLINED) {
                throw ValidationException::withMessages(['total' => __('The customer has declined two increases. Do the job at the agreed amount, or contact Sortd support.')]);
            }

            // A new proposal replaces one that's still waiting (AC10).
            $locked->finalAmountProposals()->where('status', ProposalStatus::Pending)->update(['status' => ProposalStatus::Withdrawn->value, 'updated_at' => now()]);

            $proposal = new FinalAmountProposal;
            $proposal->forceFill([
                'service_job_id' => $locked->id,
                'quote_id' => $locked->accepted_quote_id,
                'pro_id' => $pro->id,
                'version' => (int) $locked->finalAmountProposals()->max('version') + 1,
                'lines' => array_map(fn (QuoteLineData $line, int $index): array => [
                    'kind' => $line->kind->value,
                    'description' => trim($line->description),
                    'quantity' => $line->quantity,
                    'unit_price_cents' => $line->unitPriceCents,
                    'line_total_cents' => $totals->lineTotalsCents[$index],
                ], $lines, array_keys($lines)),
                'labour_cents' => $totals->labourCents,
                'materials_cents' => $totals->materialsCents,
                'callout_cents' => $totals->calloutCents,
                'vat_cents' => $totals->vatCents,
                'total_cents' => $totals->totalCents,
                'previous_total_cents' => $previous,
                'reason' => $reason,
                'status' => $increase ? ProposalStatus::Pending : ProposalStatus::Applied,
            ])->save();

            $payload = ['proposal' => $proposal->public_id, 'from_cents' => $previous, 'to_cents' => $totals->totalCents];

            if ($increase) {
                $this->stateMachine->record($locked, 'final_amount_proposed', ActorType::Pro, $user->id, $payload);
            } else {
                $locked->forceFill(['agreed_final_cents' => $totals->totalCents])->save();
                $this->stateMachine->record($locked, 'final_amount_lowered', ActorType::Pro, $user->id, $payload);
            }

            return [$proposal, $increase ? 'final_amount_proposed' : 'final_amount_lowered'];
        });

        SendFinalAmountMessage::dispatch($proposal->id, $template);

        return $proposal;
    }

    public function withdraw(User $user, FinalAmountProposal $proposal): void
    {
        $this->acceptedPro($user, $proposal->serviceJob);

        DB::transaction(function () use ($proposal): void {
            $this->lockOpenJob($proposal->serviceJob);
            $locked = FinalAmountProposal::query()->lockForUpdate()->findOrFail($proposal->id);
            abort_unless($locked->status === ProposalStatus::Pending, 404);
            $locked->forceFill(['status' => ProposalStatus::Withdrawn])->save();
        });
    }

    /** The customer approves (AC11) or declines (AC12) an increase. */
    public function decide(User $customer, FinalAmountProposal $proposal, bool $approve, ?string $note = null): void
    {
        abort_unless($proposal->serviceJob->customer_id === $customer->id, 404);
        $note = $note === null || trim($note) === '' ? null : mb_substr(trim($note), 0, 500);

        DB::transaction(function () use ($customer, $proposal, $approve, $note): void {
            $job = $this->lockOpenJob($proposal->serviceJob);
            $locked = FinalAmountProposal::query()->lockForUpdate()->findOrFail($proposal->id);

            if ($locked->status !== ProposalStatus::Pending) {
                throw new CannotQuote(__('This price change was withdrawn or already answered.'));
            }

            $locked->forceFill([
                'status' => $approve ? ProposalStatus::Approved : ProposalStatus::Declined,
                'decided_at' => now(),
                'decided_by' => $customer->id,
                'customer_note' => $note,
            ])->save();

            if ($approve) {
                $job->forceFill(['agreed_final_cents' => $locked->total_cents])->save();
            }

            $this->stateMachine->record($job, $approve ? 'final_amount_approved' : 'final_amount_declined', ActorType::Customer, $customer->id, [
                'proposal' => $locked->public_id, 'from_cents' => $locked->previous_total_cents, 'to_cents' => $locked->total_cents,
            ]);
        });

        SendFinalAmountMessage::dispatch($proposal->id, $approve ? 'final_amount_approved' : 'final_amount_declined');
    }

    /** What the job is agreed to cost: the last agreed proposal, else the accepted estimate (AC14). */
    public function agreedCents(ServiceJob $job): int
    {
        if ($job->agreed_final_cents !== null) {
            return $job->agreed_final_cents;
        }

        return (int) Quote::query()->whereKey($job->accepted_quote_id)->value('total_cents');
    }

    private function acceptedPro(User $user, ServiceJob $job): Pro
    {
        $quote = $job->accepted_quote_id === null ? null : Quote::query()->with('pro')->find($job->accepted_quote_id);
        abort_unless($quote instanceof Quote && $quote->pro->user_id === $user->id && $quote->pro->status === ProStatus::Approved, 404);

        return $quote->pro;
    }

    private function lockOpenJob(ServiceJob $job): ServiceJob
    {
        $locked = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);

        if (! in_array($locked->status, self::OPEN_STATUSES, true)) {
            throw new CannotQuote(__('The price can only change while the job is booked or in progress.'));
        }

        return $locked;
    }
}

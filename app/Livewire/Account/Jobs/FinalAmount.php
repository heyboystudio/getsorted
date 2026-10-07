<?php

declare(strict_types=1);

namespace App\Livewire\Account\Jobs;

use App\Domain\Quotes\Actions\ChangeFinalAmount;
use App\Domain\Quotes\Enums\ProposalStatus;
use App\Domain\Quotes\Exceptions\CannotQuote;
use App\Models\FinalAmountProposal;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** The customer sees price changes and approves or declines increases (spec 018, AC9–AC13). */
final class FinalAmount extends Component
{
    #[Locked]
    public string $jobPublicId;

    #[Locked]
    public bool $declining = false;

    public string $note = '';

    public function mount(string $jobPublicId): void
    {
        $this->jobPublicId = $jobPublicId;
        $this->job();
    }

    public function approve(string $publicId, ChangeFinalAmount $change): void
    {
        $this->decide($publicId, true, $change);
    }

    public function startDecline(): void
    {
        $this->declining = true;
    }

    public function decline(string $publicId, ChangeFinalAmount $change): void
    {
        $this->validate(['note' => ['nullable', 'string', 'max:500']]);
        $this->decide($publicId, false, $change);
        $this->reset(['declining', 'note']);
    }

    public function render(ChangeFinalAmount $change): View
    {
        $job = $this->job();
        $proposals = $job->finalAmountProposals()->orderByDesc('version')->get();

        return view('livewire.account.jobs.final-amount', [
            'agreedCents' => $change->agreedCents($job),
            'estimateCents' => (int) Quote::query()->whereKey($job->accepted_quote_id)->value('total_cents'),
            'pending' => $proposals->firstWhere('status', ProposalStatus::Pending),
            // Shown as news until something newer happens: a lowered price (AC13), or the customer's own answer.
            'latest' => $proposals->first(fn (FinalAmountProposal $proposal): bool => $proposal->status !== ProposalStatus::Withdrawn),
            'previousLines' => fn (FinalAmountProposal $proposal): array => $this->previousLines($proposal),
        ]);
    }

    private function decide(string $publicId, bool $approve, ChangeFinalAmount $change): void
    {
        $proposal = $this->job()->finalAmountProposals()->where('public_id', $publicId)->firstOrFail();

        try {
            $change->decide($this->user(), $proposal, $approve, $approve ? null : $this->note);
        } catch (CannotQuote $exception) {
            throw ValidationException::withMessages(['decision' => $exception->getMessage()]);
        }

        // Show the new agreed amount straight away.
        $this->jobCache = null;
    }

    /**
     * Lines the proposal changed or added, compared with what was agreed before (AC9 "changed lines highlighted").
     *
     * @return list<string> descriptions of changed lines
     */
    private function previousLines(FinalAmountProposal $proposal): array
    {
        $before = $this->job()->finalAmountProposals()->where('version', '<', $proposal->version)
            ->whereIn('status', [ProposalStatus::Approved, ProposalStatus::Applied])->latest('version')->first();
        $old = $before instanceof FinalAmountProposal
            ? array_map(fn (array $line): string => $line['description'].'|'.$line['line_total_cents'], $before->lines)
            : Quote::query()->with('lines')->findOrFail($proposal->quote_id)->lines->map(fn ($line): string => $line->description.'|'.$line->line_total_cents)->all();

        return array_values(array_map(fn (array $line): string => $line['description'], array_filter($proposal->lines,
            fn (array $line): bool => ! in_array($line['description'].'|'.$line['line_total_cents'], $old, true))));
    }

    private ?ServiceJob $jobCache = null;

    private function job(): ServiceJob
    {
        return $this->jobCache ??= ServiceJob::query()->where('public_id', $this->jobPublicId)
            ->where('customer_id', $this->user()->id)->whereNotNull('accepted_quote_id')->firstOrFail();
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 404);

        return $user;
    }
}

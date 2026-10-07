<?php

declare(strict_types=1);

namespace App\Livewire\Pros\Jobs;

use App\Domain\Quotes\Actions\ChangeFinalAmount;
use App\Domain\Quotes\Data\QuoteDraft;
use App\Domain\Quotes\Data\QuoteLineData;
use App\Domain\Quotes\Enums\LineKind;
use App\Domain\Quotes\Enums\ProposalStatus;
use App\Domain\Quotes\Exceptions\CannotQuote;
use App\Domain\Quotes\Support\QuoteCalculator;
use App\Domain\ServiceJobs\Actions\CancelOverPrice;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Models\FinalAmountProposal;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\User;
use App\Support\LocalTime;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The accepted pro's "Propose final amount" panel (spec 018, AC9–AC12). The
 * server recalculates every total; this component only collects the lines.
 */
final class FinalAmount extends Component
{
    #[Locked]
    public string $jobPublicId;

    #[Locked]
    public bool $building = false;

    /** @var list<array{kind: string, description: string, quantity: string, unitPrice: string}> */
    public array $lines = [];

    public string $reason = '';

    public function mount(string $jobPublicId): void
    {
        $this->jobPublicId = $jobPublicId;
        abort_unless($this->acceptedQuote() instanceof Quote, 404);
    }

    /** Starts from what's agreed now: the last agreed proposal's lines, else the estimate's. */
    public function start(): void
    {
        $agreed = $this->job()->finalAmountProposals()->whereIn('status', [ProposalStatus::Approved, ProposalStatus::Applied])->latest('version')->first();

        $this->lines = $agreed instanceof FinalAmountProposal
            ? array_map(fn (array $line): array => [
                'kind' => $line['kind'], 'description' => $line['description'], 'quantity' => $line['quantity'],
                'unitPrice' => number_format($line['unit_price_cents'] / 100, 2, '.', ''),
            ], $agreed->lines)
            : $this->acceptedQuote()->lines->map(fn ($line): array => [
                'kind' => $line->kind->value, 'description' => $line->description,
                'quantity' => rtrim(rtrim((string) $line->quantity, '0'), '.'),
                'unitPrice' => number_format($line->unit_price_cents / 100, 2, '.', ''),
            ])->values()->all();

        if ($this->lines === []) {
            $this->lines = [['kind' => LineKind::Labour->value, 'description' => '', 'quantity' => '1', 'unitPrice' => '']];
        }

        $this->reason = '';
        $this->building = true;
    }

    public function addLine(): void
    {
        if (count($this->lines) < 30) {
            $this->lines[] = ['kind' => LineKind::Materials->value, 'description' => '', 'quantity' => '1', 'unitPrice' => ''];
        }
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function cancelBuilding(): void
    {
        $this->building = false;
        $this->resetErrorBag();
    }

    public function propose(ChangeFinalAmount $change): void
    {
        try {
            $change->propose($this->user(), $this->job(), $this->lineData(), $this->reason);
        } catch (CannotQuote $exception) {
            throw ValidationException::withMessages(['total' => $exception->getMessage()]);
        }

        $this->building = false;
        $this->jobCache = null;
    }

    public function withdraw(string $publicId, ChangeFinalAmount $change): void
    {
        try {
            $change->withdraw($this->user(), $this->job()->finalAmountProposals()->where('public_id', $publicId)->firstOrFail());
        } catch (CannotQuote $exception) {
            throw ValidationException::withMessages(['total' => $exception->getMessage()]);
        }
    }

    public function cancelJob(CancelOverPrice $cancel): void
    {
        try {
            $cancel->handle($this->user(), $this->job());
        } catch (CannotQuote $exception) {
            throw ValidationException::withMessages(['cancel' => $exception->getMessage()]);
        }
    }

    public function render(QuoteCalculator $calculator, ChangeFinalAmount $change): View
    {
        $job = $this->job();
        $proposals = $job->finalAmountProposals()->orderByDesc('version')->get();
        $latest = $proposals->first();
        $declined = $proposals->where('status', ProposalStatus::Declined)->count();
        $open = in_array($job->status, ChangeFinalAmount::OPEN_STATUSES, true);

        return view('livewire.pros.jobs.final-amount', [
            'job' => $job,
            'agreedCents' => $change->agreedCents($job),
            'estimateCents' => $this->acceptedQuote()->total_cents,
            'proposals' => $proposals,
            'pending' => $proposals->firstWhere('status', ProposalStatus::Pending),
            'canPropose' => $open && $proposals->where('status', ProposalStatus::Pending)->isEmpty(),
            'increaseBlocked' => $declined >= 2,
            'canCancel' => $job->status === ServiceJobStatus::Scheduled && $latest?->status === ProposalStatus::Declined,
            'preview' => $this->building ? $this->previewCents($calculator) : null,
            'lineKinds' => LineKind::cases(),
        ]);
    }

    /** A live total while typing; null until every line has a valid amount. */
    private function previewCents(QuoteCalculator $calculator): ?int
    {
        try {
            $lines = $this->lineData();
        } catch (ValidationException|MathException) {
            return null;
        }

        return $lines === [] ? null : $calculator->calculate(new QuoteDraft($lines, 0, LocalTime::today(), 7, null), $this->acceptedQuote()->pro->isVatRegistered())->totalCents;
    }

    /** @return list<QuoteLineData> */
    private function lineData(): array
    {
        $data = [];

        foreach ($this->lines as $index => $line) {
            $kind = LineKind::tryFrom($line['kind']);
            $price = trim($line['unitPrice']);

            if (! $kind instanceof LineKind) {
                throw ValidationException::withMessages(["lines.{$index}.kind" => __('Choose what this line is for.')]);
            }

            if (preg_match('/^\d{1,7}(\.\d{1,2})?$/', $price) !== 1) {
                throw ValidationException::withMessages(["lines.{$index}.unit_price" => __('Enter a price in rand, e.g. 450 or 450.50.')]);
            }

            $data[] = new QuoteLineData($kind, $line['description'], trim($line['quantity']), BigDecimal::of($price)->multipliedBy(100)->toInt());
        }

        return $data;
    }

    private ?ServiceJob $jobCache = null;

    private function job(): ServiceJob
    {
        return $this->jobCache ??= ServiceJob::query()->where('public_id', $this->jobPublicId)->firstOrFail();
    }

    private function acceptedQuote(): ?Quote
    {
        $job = $this->job();
        $quote = $job->accepted_quote_id === null ? null : Quote::query()->with(['lines', 'pro'])->find($job->accepted_quote_id);

        return $quote instanceof Quote && $quote->pro->user_id === $this->user()->id ? $quote : null;
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 404);

        return $user;
    }
}

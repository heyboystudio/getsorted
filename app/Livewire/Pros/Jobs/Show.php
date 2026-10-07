<?php

declare(strict_types=1);

namespace App\Livewire\Pros\Jobs;

use App\Domain\Assistant\Support\Redactor;
use App\Domain\Matching\Actions\AcceptInvite;
use App\Domain\Matching\Actions\DeclineInvite;
use App\Domain\Matching\Actions\OpenInvite;
use App\Domain\Matching\Enums\DeclineReason;
use App\Domain\Matching\Enums\InviteStatus;
use App\Domain\Matching\Exceptions\CannotInvite;
use App\Domain\Matching\Support\Distance;
use App\Domain\Quotes\Actions\ReviseQuote;
use App\Domain\Quotes\Actions\SubmitQuote;
use App\Domain\Quotes\Actions\WithdrawQuote;
use App\Domain\Quotes\Data\QuoteDraft;
use App\Domain\Quotes\Data\QuoteLineData;
use App\Domain\Quotes\Data\QuoteTotals;
use App\Domain\Quotes\Enums\LineKind;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Domain\Quotes\Exceptions\CannotQuote;
use App\Domain\Quotes\Support\ContactMasker;
use App\Domain\Quotes\Support\QuoteCalculator;
use App\Domain\Quotes\Support\QuoteFlow;
use App\Domain\Quotes\Support\QuoteRules;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Support\JobChat;
use App\Livewire\Pros\Jobs\Concerns\EnsuresApprovedPro;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use App\Settings\QuoteSettings;
use App\Support\LocalTime;
use App\Support\Rand;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * One invite as the pro sees it (spec 009, AC8–AC10), with the quote builder,
 * the pro's sent quote and, once their quote is accepted, the customer's
 * contact details and address (spec 010, AC1–AC5, AC9). Before acceptance the
 * pro never sees the customer's identity, contact details or street address.
 */
#[Layout('components.layouts.panel', ['panel' => 'pro', 'focused' => true])]
#[Title('Job')]
final class Show extends Component
{
    use EnsuresApprovedPro;

    #[Locked]
    public string $invitePublicId;

    #[Locked]
    public bool $unavailable = false;

    public string $reason = '';

    public string $note = '';

    #[Locked]
    public bool $building = false;

    #[Locked]
    public bool $previewing = false;

    #[Locked]
    public bool $sent = false;

    /** @var list<array{kind: string, description: string, quantity: string, unitPrice: string}> */
    public array $lines = [];

    public int $depositPercent = 0;

    public string $earliestStartDate = '';

    public int $validityDays = 7;

    public string $notes = '';

    public string $withdrawReason = '';

    public function mount(ServiceJobInvite $invite, OpenInvite $openInvite): void
    {
        if (! $this->ensureApprovedPro()) {
            return;
        }

        abort_unless($invite->pro_id === $this->currentPro()->id, 404);
        $this->invitePublicId = $invite->public_id;

        if ($invite->status === InviteStatus::Quoted || $this->myQuote() instanceof Quote) {
            return;
        }

        try {
            $openInvite->handle($this->currentUser(), $invite);
        } catch (CannotInvite) {
            $this->unavailable = true;
        }
    }

    public function decline(DeclineInvite $declineInvite): void
    {
        $this->resetErrorBag();
        $reason = DeclineReason::tryFrom($this->reason);

        if (! $reason instanceof DeclineReason) {
            throw ValidationException::withMessages(['reason' => __('Choose a reason.')]);
        }

        try {
            $declineInvite->handle($this->currentUser(), $this->invite(), $reason, $this->note);
        } catch (CannotInvite $exception) {
            $this->unavailable = true;
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }

        $this->redirectRoute('pros.jobs');
    }

    public function acceptJob(AcceptInvite $acceptInvite): void
    {
        $this->resetErrorBag();

        try {
            $acceptInvite->handle($this->currentUser(), $this->invite());
        } catch (CannotInvite $exception) {
            $this->unavailable = true;
            throw ValidationException::withMessages(['accept' => $exception->getMessage()]);
        }
    }

    public function startQuote(QuoteSettings $settings): void
    {
        $this->resetErrorBag();
        abort_unless($this->invite()->status === InviteStatus::Accepted, 403);
        $this->lines = [['kind' => LineKind::Labour->value, 'description' => '', 'quantity' => '1', 'unitPrice' => '']];
        $this->depositPercent = 0;
        $this->earliestStartDate = LocalTime::today()->addDay()->toDateString();
        $this->validityDays = $settings->default_validity_days;
        $this->notes = '';
        $this->building = true;
        $this->previewing = false;
    }

    /** Opens the builder filled with the pro's current quote (AC5, AC12). */
    public function startRevision(): void
    {
        $quote = $this->myQuote();
        abort_unless($quote instanceof Quote, 404);
        $quote->load('lines');

        $this->resetErrorBag();
        $this->lines = $quote->lines->map(fn ($line): array => [
            'kind' => $line->kind->value,
            'description' => $line->description,
            'quantity' => rtrim(rtrim((string) $line->quantity, '0'), '.'),
            'unitPrice' => (string) BigDecimal::ofUnscaledValue($line->unit_price_cents, 2),
        ])->values()->all();
        $this->depositPercent = $quote->deposit_percent;
        $this->earliestStartDate = max($quote->earliest_start_date->toDateString(), LocalTime::today()->toDateString());
        $this->validityDays = max(1, (int) $quote->submitted_at->setTimezone(LocalTime::timezone())->startOfDay()->diffInDays($quote->valid_until));
        $this->notes = (string) $quote->notes;
        $this->building = true;
        $this->previewing = false;
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

    public function editQuote(): void
    {
        $this->previewing = false;
    }

    /** Checks the quote and shows it as the customer will see it, with the estimated payout (AC2). */
    public function preview(QuoteCalculator $calculator, QuoteRules $rules): void
    {
        $this->resetErrorBag();
        $draft = $this->draft();
        $totals = $calculator->calculate($draft, $this->currentPro()->isVatRegistered());
        $this->remap(fn () => $rules->check($draft, $totals));
        $this->previewing = true;
    }

    public function submitQuote(SubmitQuote $submitQuote, ReviseQuote $reviseQuote): void
    {
        // Only from the preview, so a double tap or retry cannot send a second version (AC3).
        if (! $this->building || ! $this->previewing) {
            return;
        }

        $this->resetErrorBag();
        $draft = $this->draft();
        $existing = $this->myQuote();

        try {
            $this->remap(fn () => $existing instanceof Quote
                ? $reviseQuote->handle($this->currentUser(), $existing, $draft)
                : $submitQuote->handle($this->currentUser(), $this->invite(), $draft));
        } catch (CannotQuote $exception) {
            throw ValidationException::withMessages(['quote' => $exception->getMessage()]);
        }

        $this->building = false;
        $this->previewing = false;
        $this->sent = true;
    }

    public function withdraw(WithdrawQuote $withdrawQuote): void
    {
        $this->resetErrorBag();
        $quote = $this->myQuote();
        abort_unless($quote instanceof Quote, 404);

        try {
            $this->remap(fn () => $withdrawQuote->handle($this->currentUser(), $quote, $this->withdrawReason));
        } catch (CannotQuote $exception) {
            throw ValidationException::withMessages(['withdrawReason' => $exception->getMessage()]);
        }

        $this->redirectRoute('pros.jobs');
    }

    public function render(QuoteCalculator $calculator): View
    {
        $invite = $this->invite();
        $quote = $this->myQuote()?->load('lines');
        $job = ServiceJob::query()->with(['trade', 'property', 'customer', 'media'])->findOrFail($invite->service_job_id);
        $accepted = $quote instanceof Quote && $quote->status === QuoteStatus::Accepted && $job->accepted_quote_id === $quote->id;
        $canQuote = ! $quote instanceof Quote && ! $this->unavailable && $invite->isAvailable();
        $hasOpenQuote = $quote instanceof Quote && in_array($quote->status, [QuoteStatus::Submitted, QuoteStatus::Expired], true) && $job->status === ServiceJobStatus::Open;

        if (! $accepted && ! $canQuote && ! $hasOpenQuote && ! $this->sent) {
            $full = $invite->status === InviteStatus::Closed && $job->status === ServiceJobStatus::Open && $job->quotes_count >= QuoteFlow::maxQuotes();

            return view('livewire.pros.jobs.show', ['invite' => $invite, 'job' => null, 'full' => $full]);
        }

        $description = $job->ai_summary === null ? null : Redactor::strip($job->ai_summary);
        $notes = $job->customer_notes === null ? null : Redactor::strip($job->customer_notes);

        return view('livewire.pros.jobs.show', [
            'invite' => $invite,
            'job' => $job,
            // The facts Siya extracted, highlighted so the pro can decide whether they can help (spec 020).
            'facts' => array_map(static fn (string $fact): string => Redactor::strip($fact), $job->factTexts()),
            'distance' => $invite->pro->base_location !== null && $job->location !== null ? Distance::label($invite->pro->base_location, $job->location) : null,
            'description' => $description === '' ? null : $description,
            // Not "notes": that name is the quote builder's own field on this component.
            'customerNotes' => $notes === '' || $notes === $description ? null : $notes,
            'photoUrls' => $job->getMedia(ServiceJob::PHOTO_COLLECTION)->map(fn ($photo): string => $invite->photoUrl($photo))->all(),
            'invitedCount' => $job->invites()->count(),
            'quotesCount' => $job->quotes_count,
            'reasons' => DeclineReason::cases(),
            'canQuote' => $canQuote,
            'jobAccepted' => $invite->status === InviteStatus::Accepted,
            'quote' => $quote,
            'accepted' => $accepted,
            // Contact details only for the pro whose quote was accepted (AC9).
            'contact' => $accepted && $this->currentUser()->can('viewContact', $job) ? [
                'name' => $job->customer->first_name,
                'phone' => $job->customer->phone_e164,
                'label' => $job->property?->label,
                'address' => $job->property?->street_address,
                'suburb' => $job->area_label,
            ] : null,
            'previewTotals' => $this->building && $this->previewing ? $this->previewTotals($calculator) : null,
            'previewText' => $this->building && $this->previewing ? $this->previewText() : null,
            'lineKinds' => LineKind::cases(),
            // Spec 018: the chat shows while this pro can still write, or once a conversation exists.
            'chat' => JobChat::canWrite($job, $invite->pro) || $job->conversations()->where('pro_id', $invite->pro_id)->exists(),
            'maxDeposit' => app(QuoteSettings::class)->max_deposit_percent,
        ]);
    }

    /**
     * Line descriptions and notes masked exactly as the customer will see them (AC2, AC6).
     *
     * @return array{lines: list<string>, notes: ?string, start: ?string, validUntil: string}
     */
    private function previewText(): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d', $this->earliestStartDate, LocalTime::timezone());
        $notes = trim($this->notes) === '' ? null : ContactMasker::mask($this->notes)[0];

        return [
            'lines' => array_map(static fn (array $line): string => ContactMasker::mask((string) $line['description'])[0], $this->lines),
            'notes' => $notes,
            'start' => $start instanceof CarbonImmutable ? $start->translatedFormat('D j M') : null,
            'validUntil' => LocalTime::today()->addDays(max(1, $this->validityDays))->translatedFormat('D j M'),
        ];
    }

    private function previewTotals(QuoteCalculator $calculator): ?QuoteTotals
    {
        try {
            return $calculator->calculate($this->draft(), $this->currentPro()->isVatRegistered());
        } catch (ValidationException) {
            return null;
        }
    }

    /** The builder's fields as a domain draft; rand amounts become cents here (AC2). */
    private function draft(): QuoteDraft
    {
        $errors = [];
        $lines = [];

        foreach ($this->lines as $index => $line) {
            $cents = Rand::toCents((string) $line['unitPrice']);
            $kind = LineKind::tryFrom((string) $line['kind']);

            if ($cents === null) {
                $errors["lines.{$index}.unitPrice"] = __('Enter a price in rand, e.g. 350 or 120.50.');
            }

            if (! $kind instanceof LineKind) {
                $errors["lines.{$index}.kind"] = __('Choose labour, materials or call-out.');
            }

            $lines[] = new QuoteLineData($kind ?? LineKind::Labour, (string) $line['description'], trim((string) $line['quantity']), $cents ?? 0);
        }

        $start = CarbonImmutable::createFromFormat('Y-m-d', $this->earliestStartDate, LocalTime::timezone());

        if (! $start instanceof CarbonImmutable) {
            $errors['earliestStartDate'] = __('Choose a start date.');
        }

        if ($errors !== []) {
            // Also report line problems the domain would find, so every field is marked at once.
            try {
                app(QuoteRules::class)->check(new QuoteDraft($lines, $this->depositPercent, $start ?: LocalTime::today(), $this->validityDays, $this->notes), app(QuoteCalculator::class)->calculate(new QuoteDraft($lines, 0, LocalTime::today(), 7, null), false));
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $key => $messages) {
                    $errors[$this->field($key)] ??= $messages[0];
                }
            }

            throw ValidationException::withMessages($errors);
        }

        return new QuoteDraft($lines, $this->depositPercent, $start->startOfDay(), $this->validityDays, $this->notes === '' ? null : $this->notes);
    }

    /** Runs a domain call and renames its error keys to the builder's fields. */
    private function remap(callable $call): mixed
    {
        try {
            return $call();
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $errors[$this->field($key)] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }
    }

    private function field(string $key): string
    {
        return match (true) {
            str_ends_with($key, '.unit_price') => str_replace('.unit_price', '.unitPrice', $key),
            $key === 'deposit_percent' => 'depositPercent',
            $key === 'earliest_start_date' => 'earliestStartDate',
            $key === 'validity_days' => 'validityDays',
            $key === 'withdraw_reason' => 'withdrawReason',
            default => $key,
        };
    }

    private function myQuote(): ?Quote
    {
        $invite = $this->invite();

        return Quote::query()->where('service_job_id', $invite->service_job_id)->where('pro_id', $invite->pro_id)
            ->orderByDesc('version')->first();
    }

    private function invite(): ServiceJobInvite
    {
        return ServiceJobInvite::query()->where('public_id', $this->invitePublicId)->where('pro_id', $this->currentPro()->id)->firstOrFail();
    }
}

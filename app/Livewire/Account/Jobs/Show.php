<?php

declare(strict_types=1);

namespace App\Livewire\Account\Jobs;

use App\Domain\Quotes\Actions\AcceptQuote;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Domain\Quotes\Exceptions\CannotQuote;
use App\Domain\ServiceJobs\Actions\CancelBookedJob;
use App\Domain\ServiceJobs\Actions\CancelJobByCustomer;
use App\Domain\ServiceJobs\Actions\MarkJobDone;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Exceptions\CannotCancelJob;
use App\Domain\ServiceJobs\Exceptions\CannotFinishJob;
use App\Domain\ServiceJobs\Support\JobChat;
use App\Domain\ServiceJobs\Support\JobStages;
use App\Domain\ServiceJobs\Support\JobTimeline;
use App\Models\JobConversation;
use App\Models\Pro;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** A customer's own job: details, estimates to compare (spec 010) and chats with pros (spec 018). */
#[Layout('components.layouts.panel', ['panel' => 'customer'])]
final class Show extends Component
{
    #[Locked]
    public string $publicId;

    public function mount(ServiceJob $job): void
    {
        /** @var User $user */
        $user = auth()->user();

        // 404, not 403, so other customers' jobs are not revealed.
        abort_unless($user->can('view', $job) && $job->customer_id === $user->id, 404);

        $this->publicId = $job->public_id;
    }

    #[Locked]
    public ?string $acceptingQuote = null;

    /** The pro whose chat is open (spec 018); only pros in this job's chat list can be chosen. */
    #[Locked]
    public ?string $chatWith = null;

    public function openChat(string $proPublicId): void
    {
        abort_unless($this->chats()->contains(fn (array $chat): bool => $chat['pro']->public_id === $proPublicId), 404);
        $this->chatWith = $proPublicId;
    }

    /** Opens the confirmation for one quote (spec 010, AC8). */
    public function confirmAccept(string $quotePublicId): void
    {
        $this->resetErrorBag();
        abort_unless($this->quoteOnJob($quotePublicId) instanceof Quote, 404);
        $this->acceptingQuote = $quotePublicId;
    }

    public function cancelAccept(): void
    {
        $this->acceptingQuote = null;
    }

    public function accept(AcceptQuote $acceptQuote): void
    {
        $quote = $this->acceptingQuote === null ? null : $this->quoteOnJob($this->acceptingQuote);
        abort_unless($quote instanceof Quote, 404);

        try {
            /** @var User $user */
            $user = auth()->user();
            $acceptQuote->handle($user, $quote);
        } catch (CannotQuote $exception) {
            throw ValidationException::withMessages(['accept' => $exception->getMessage()]);
        } finally {
            $this->acceptingQuote = null;
        }
    }

    public bool $confirmingCancel = false;

    public string $cancelReason = '';

    public function confirmCancel(): void
    {
        $this->resetErrorBag();
        $this->confirmingCancel = true;
    }

    public function keepJob(): void
    {
        $this->confirmingCancel = false;
    }

    public function cancelJob(CancelJobByCustomer $cancel): void
    {
        $this->validate(['cancelReason' => ['nullable', 'string', 'max:300']]);

        /** @var User $user */
        $user = auth()->user();
        $job = ServiceJob::query()->where('public_id', $this->publicId)->where('customer_id', $user->id)->firstOrFail();

        try {
            $cancel->handle($user, $job, $this->cancelReason);
        } catch (CannotCancelJob $exception) {
            $this->confirmingCancel = false;

            throw ValidationException::withMessages(['cancel' => $exception->getMessage()]);
        }

        $this->confirmingCancel = false;
        $this->cancelReason = '';
    }

    // ── After booking (spec 024) ──

    public bool $confirmingDone = false;

    public bool $confirmingBookedCancel = false;

    public string $bookedCancelReason = '';

    public function confirmDone(): void
    {
        $this->resetErrorBag();
        $this->confirmingDone = true;
        $this->confirmingBookedCancel = false;
    }

    public function confirmBookedCancel(): void
    {
        $this->resetErrorBag();
        $this->confirmingBookedCancel = true;
        $this->confirmingDone = false;
    }

    public function keepBooking(): void
    {
        $this->confirmingDone = false;
        $this->confirmingBookedCancel = false;
    }

    public function markDone(MarkJobDone $markJobDone): void
    {
        try {
            $markJobDone->handle($this->customer(), $this->ownJob());
        } catch (CannotFinishJob $exception) {
            throw ValidationException::withMessages(['finish' => $exception->getMessage()]);
        } finally {
            $this->confirmingDone = false;
        }
    }

    public function cancelBooked(CancelBookedJob $cancelBookedJob): void
    {
        try {
            $cancelBookedJob->handle($this->customer(), $this->ownJob(), $this->bookedCancelReason);
        } catch (CannotCancelJob $exception) {
            throw ValidationException::withMessages(['bookedCancelReason' => $exception->getMessage()]);
        }

        $this->confirmingBookedCancel = false;
        $this->bookedCancelReason = '';
    }

    private function customer(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private function ownJob(): ServiceJob
    {
        return ServiceJob::query()->where('public_id', $this->publicId)->where('customer_id', $this->customer()->id)->firstOrFail();
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $job = ServiceJob::query()->where('public_id', $this->publicId)->where('customer_id', $user->id)
            ->with(['trade', 'property'])->firstOrFail();

        return view('livewire.account.jobs.show', [
            'job' => $job,
            'justPosted' => session('job_posted') === true,
            'stages' => JobStages::for($job->status),
            'timeline' => JobTimeline::forJob($job),
            // The same trade again; the customer describes the new problem and picks the address in the thread (spec 021, AC11).
            'bookAgain' => in_array($job->status, ServiceJobStatus::finished(), true) && $job->trade->is_active
                ? route('book.trade', $job->trade)
                : null,
            // A count only: customers never see who was invited (spec 009, AC13).
            'invitedCount' => $job->invites()->count(),
            // Current quotes to compare, or the accepted one (spec 010, AC7–AC9).
            'quotes' => $job->quotes()->whereIn('status', [QuoteStatus::Submitted, QuoteStatus::Accepted])
                ->with(['lines', 'pro.user', 'pro.documents.media'])->orderBy('total_cents')->get(),
            'accepting' => $this->acceptingQuote === null ? null : $this->quoteOnJob($this->acceptingQuote),
            'chats' => $chats = $this->chats(),
            'openChat' => $chats->first(fn (array $chat): bool => $chat['pro']->public_id === $this->chatWith)
                ?? $chats->first(fn (array $chat): bool => $chat['writable'] && $job->accepted_quote_id !== null),
        ])->title($job->trade->name);
    }

    /** @return Collection<int, array{pro: Pro, label: string, named: bool, conversation: ?JobConversation, writable: bool, unread: int}> */
    private function chats(): Collection
    {
        /** @var User $user */
        $user = auth()->user();

        return JobChat::customerList(ServiceJob::query()->where('public_id', $this->publicId)->where('customer_id', $user->id)->firstOrFail());
    }

    private function quoteOnJob(string $quotePublicId): ?Quote
    {
        /** @var User $user */
        $user = auth()->user();

        return Quote::query()->where('public_id', $quotePublicId)
            ->whereHas('serviceJob', fn ($job) => $job->where('public_id', $this->publicId)->where('customer_id', $user->id))
            ->first();
    }
}

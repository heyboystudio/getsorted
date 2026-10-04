<?php

declare(strict_types=1);

namespace App\Livewire\Pros\Jobs;

use App\Domain\Assistant\Support\Redactor;
use App\Domain\Matching\Actions\DeclineInvite;
use App\Domain\Matching\Actions\OpenInvite;
use App\Domain\Matching\Enums\DeclineReason;
use App\Domain\Matching\Enums\InviteStatus;
use App\Domain\Matching\Exceptions\CannotInvite;
use App\Livewire\Pros\Jobs\Concerns\EnsuresApprovedPro;
use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * One invite as the pro sees it (spec 009, AC8–AC10): never the customer's
 * name, contact details or street address; description and notes have contact
 * details stripped (founder decision 3).
 */
#[Layout('components.layouts.app')]
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

    public function mount(ServiceJobInvite $invite, OpenInvite $openInvite): void
    {
        if (! $this->ensureApprovedPro()) {
            return;
        }

        abort_unless($invite->pro_id === $this->currentPro()->id, 404);
        $this->invitePublicId = $invite->public_id;

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

    public function render(): View
    {
        $invite = $this->invite();

        if ($this->unavailable || ! $invite->isAvailable()) {
            return view('livewire.pros.jobs.show', ['invite' => $invite, 'job' => null]);
        }

        /** @var ServiceJob $job */
        $job = ServiceJob::query()->with(['service.trade', 'service.questions', 'property.suburb', 'media'])->findOrFail($invite->service_job_id);
        $description = $job->ai_summary === null ? null : Redactor::strip($job->ai_summary);
        $notes = $job->customer_notes === null ? null : Redactor::strip($job->customer_notes);

        return view('livewire.pros.jobs.show', [
            'invite' => $invite,
            'job' => $job,
            'answers' => array_map(static function (array $answer): array {
                $answer['answer'] = is_array($answer['answer'])
                    ? array_map(static fn (mixed $value): string => Redactor::strip((string) $value), $answer['answer'])
                    : Redactor::strip((string) $answer['answer']);

                return $answer;
            }, $job->orderedAnswers()),
            'description' => $description === '' ? null : $description,
            'notes' => $notes === '' || $notes === $description ? null : $notes,
            'photoUrls' => $job->getMedia(ServiceJob::PHOTO_COLLECTION)->map(fn ($photo): string => $invite->photoUrl($photo))->all(),
            'invitedCount' => $job->invites()->count(),
            'quotesCount' => $job->invites()->where('status', InviteStatus::Quoted)->count(),
            'reasons' => DeclineReason::cases(),
        ]);
    }

    private function invite(): ServiceJobInvite
    {
        return ServiceJobInvite::query()->where('public_id', $this->invitePublicId)->where('pro_id', $this->currentPro()->id)->firstOrFail();
    }
}

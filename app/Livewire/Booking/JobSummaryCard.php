<?php

declare(strict_types=1);

namespace App\Livewire\Booking;

use App\Domain\Assistant\Actions\EditJobSummary;
use App\Domain\Assistant\Actions\SummariseDraft;
use App\Domain\Assistant\Support\AssistantCalls;
use App\Domain\Assistant\Support\SummaryRules;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\SummarySource;
use App\Domain\ServiceJobs\Support\JobSummaryInput;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The review step's "Job description for pros" card (spec 007, AC5–AC7). A
 * separate component so a slow assistant call never holds up posting or
 * changing other details on the review step.
 */
final class JobSummaryCard extends Component
{
    #[Locked]
    public string $jobPublicId;

    #[Locked]
    public bool $editing = false;

    #[Locked]
    public ?string $attemptedHash = null;

    public string $text = '';

    public function load(SummariseDraft $summariseDraft): void
    {
        $job = $this->draft();
        $summariseDraft->handle($this->customer(), $job);
        // Tried for these details: show nothing rather than a loader if it failed.
        $this->attemptedHash = JobSummaryInput::hash($job);
    }

    public function edit(): void
    {
        $this->text = (string) $this->draft()->ai_summary;
        $this->editing = true;
    }

    public function save(EditJobSummary $editJobSummary): void
    {
        try {
            $editJobSummary->handle($this->customer(), $this->draft(), $this->text);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['text' => $exception->validator->errors()->first('summary')]);
        }

        $this->editing = false;
    }

    public function cancel(): void
    {
        $this->editing = false;
        $this->text = '';
    }

    public function render(): View
    {
        $job = $this->draft();
        $current = $job->ai_summary_input_hash === JobSummaryInput::hash($job);

        return view('livewire.booking.job-summary-card', [
            'state' => match (true) {
                $job->ai_summary_source === SummarySource::CustomerEdited => 'edited',
                $job->ai_summary_source === SummarySource::Ai && $current => 'ai',
                ! $current && $this->attemptedHash !== JobSummaryInput::hash($job) && app(AssistantCalls::class)->available() => 'loading',
                default => 'none',
            },
            'summary' => $job->ai_summary,
            'stale' => $job->ai_summary_source === SummarySource::CustomerEdited && ! $current,
            'maxLength' => SummaryRules::MAX_LENGTH,
        ]);
    }

    /** Only the signed-in customer's own draft; anything else is not found. */
    private function draft(): ServiceJob
    {
        return ServiceJob::query()->where('public_id', $this->jobPublicId)
            ->where('customer_id', $this->customer()->id)->where('status', ServiceJobStatus::Draft)->firstOrFail();
    }

    private function customer(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 404);

        return $user;
    }
}

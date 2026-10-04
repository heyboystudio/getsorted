<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Contracts\Data\ScopingSummaryReply;
use App\Contracts\ScopingAssistant;
use App\Domain\Assistant\Enums\AiOutcome;
use App\Domain\Assistant\Enums\AiPurpose;
use App\Domain\Assistant\Support\AssistantCalls;
use App\Domain\Assistant\Support\Redactor;
use App\Domain\Assistant\Support\SummaryRules;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\SummarySource;
use App\Domain\ServiceJobs\Support\JobSummaryInput;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Asks the assistant for a neutral job description when the draft's details
 * have changed since the last one; never overwrites a customer's edit (spec 007, AC5–AC7).
 */
final readonly class SummariseDraft
{
    public function __construct(private AssistantCalls $calls) {}

    public function handle(User $customer, ServiceJob $job): void
    {
        Gate::forUser($customer)->authorize('update', $job);
        $job->loadMissing('service');
        $hash = JobSummaryInput::hash($job);

        if ($job->status !== ServiceJobStatus::Draft || $job->ai_summary_source === SummarySource::CustomerEdited
            || $job->ai_summary_input_hash === $hash || ! $this->calls->available()) {
            return;
        }

        [$outcome, $reply] = $this->calls->call(
            AiPurpose::Summarise,
            'assistant:summary:'.$job->id,
            (int) config('sortd.ai.summaries_per_hour'),
            fn (ScopingAssistant $assistant): ScopingSummaryReply => $assistant->summarise(
                $job->service->key, JobSummaryInput::redactedAnswers($job), Redactor::strip((string) $job->customer_notes),
            ),
            fn (ScopingSummaryReply $reply): bool => SummaryRules::acceptable($reply->summary),
            $job->id,
        );

        // Timeouts, errors and throttling leave the draft alone so a later visit can try again.
        if (! in_array($outcome, [AiOutcome::Ok, AiOutcome::Invalid], true)) {
            return;
        }

        DB::transaction(function () use ($job, $hash, $reply): void {
            $fresh = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);

            // Details changed or the customer edited while the model was writing: this summary is stale.
            if ($fresh->status !== ServiceJobStatus::Draft || $fresh->ai_summary_source === SummarySource::CustomerEdited
                || JobSummaryInput::hash($fresh) !== $hash) {
                return;
            }

            $summary = $reply?->summary === null ? null : trim($reply->summary);

            $fresh->forceFill([
                'ai_summary' => $summary,
                'ai_summary_source' => $summary === null ? SummarySource::None : SummarySource::Ai,
                'ai_summary_generated_at' => $summary === null ? null : now(),
                'ai_summary_input_hash' => $hash,
            ])->save();
        });
    }
}

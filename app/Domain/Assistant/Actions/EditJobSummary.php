<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\Support\SummaryRules;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\SummarySource;
use App\Domain\ServiceJobs\Support\JobSummaryInput;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** The customer's own wording replaces the AI text and is never sent to the assistant (spec 007, AC7; founder decision 3). */
final class EditJobSummary
{
    /** @throws ValidationException */
    public function handle(User $customer, ServiceJob $job, string $summary): ServiceJob
    {
        Gate::forUser($customer)->authorize('update', $job);
        $summary = trim($summary);

        Validator::make(['summary' => $summary], ['summary' => ['required', 'string', 'max:'.SummaryRules::MAX_LENGTH]], [
            'summary.required' => __('Enter a description, or cancel to keep the current one.'),
            'summary.max' => __('The description can be up to :max characters.', ['max' => SummaryRules::MAX_LENGTH]),
        ])->validate();

        return DB::transaction(function () use ($job, $summary): ServiceJob {
            $fresh = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);
            abort_unless($fresh->status === ServiceJobStatus::Draft, 404);

            $fresh->forceFill([
                'ai_summary' => $summary,
                'ai_summary_source' => SummarySource::CustomerEdited,
                'ai_summary_input_hash' => JobSummaryInput::hash($fresh),
            ])->save();

            return $fresh;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Actions;

use App\Domain\ServiceJobs\Data\BookingData;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\SummarySource;
use App\Domain\ServiceJobs\Enums\TimeWindow;
use App\Domain\ServiceJobs\Enums\Urgency;
use App\Domain\ServiceJobs\Exceptions\CannotPostServiceJob;
use App\Domain\ServiceJobs\Support\ScopingAnswers;
use App\Models\Property;
use App\Models\Service;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SaveBookingDraft
{
    /**
     * Creates or updates a customer's draft job as they move through the
     * booking wizard (autosave, spec 005). Customers may hold a limited number
     * of drafts; urgency follows the answers and the chosen window.
     *
     * @throws CannotPostServiceJob when the draft limit is reached
     */
    public function handle(User $customer, Service $service, ?ServiceJob $job, BookingData $data): ServiceJob
    {
        $notes = $data->notes === null ? null : trim($data->notes);

        if ($notes !== null && mb_strlen($notes) > (int) config('sortd.jobs.notes_max_length')) {
            throw new CannotPostServiceJob(__('Notes can be up to :max characters.', ['max' => config('sortd.jobs.notes_max_length')]));
        }

        return DB::transaction(function () use ($customer, $service, $job, $data, $notes): ServiceJob {
            if ($job instanceof ServiceJob) {
                $job = ServiceJob::query()->lockForUpdate()->findOrFail($job->id);
                Gate::forUser($customer)->authorize('update', $job);
            } else {
                Gate::forUser($customer)->authorize('create', ServiceJob::class);
                User::query()->lockForUpdate()->findOrFail($customer->id);

                $drafts = ServiceJob::query()->where('customer_id', $customer->id)->where('status', ServiceJobStatus::Draft)->count();

                if ($drafts >= (int) config('sortd.jobs.max_drafts')) {
                    throw new CannotPostServiceJob(__('You have too many unfinished requests. Finish or remove one first.'));
                }

                $job = new ServiceJob;
                $job->customer()->associate($customer);
                $job->service()->associate($service);
            }

            // A corrected classification updates the same authorised draft, retaining its photos.
            if ($job->service_id !== $service->id) {
                $job->service()->associate($service);
                $job->ai_summary = null;
                $job->ai_summary_source = SummarySource::None;
                $job->ai_summary_generated_at = null;
                $job->ai_summary_input_hash = null;
            }

            $property = $data->propertyPublicId === null ? null
                : $customer->properties()->where('public_id', $data->propertyPublicId)->first();

            $job->property()->associate($property instanceof Property ? $property : null);
            $job->fill([
                'scoping_answers' => $data->answers,
                'customer_notes' => $notes === '' ? null : $notes,
                'preferred_date' => $data->preferredDate?->toDateString(),
                'time_window' => $data->timeWindow,
                'urgency' => $data->timeWindow === TimeWindow::Today || ScopingAnswers::isUrgent($service, $data->answers) ? Urgency::Urgent : Urgency::Normal,
            ]);
            $job->save();

            return $job;
        });
    }
}

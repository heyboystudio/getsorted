<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Actions;

use App\Domain\Assistant\Support\SummaryRules;
use App\Domain\Matching\EligibleProsQuery;
use App\Domain\ServiceJobs\Enums\ActorType;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\SummarySource;
use App\Domain\ServiceJobs\Enums\TimeWindow;
use App\Domain\ServiceJobs\Exceptions\CannotPostServiceJob;
use App\Domain\ServiceJobs\Exceptions\NoEligiblePros;
use App\Domain\ServiceJobs\ServiceJobStateMachine;
use App\Domain\ServiceJobs\Support\JobSummaryInput;
use App\Jobs\SendJobPostedMessage;
use App\Jobs\StartMatching;
use App\Models\Property;
use App\Models\ServiceJob;
use App\Models\User;
use App\Settings\JobTimers;
use App\Support\LocalTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

final readonly class PostServiceJob
{
    public function __construct(
        private ServiceJobStateMachine $stateMachine,
        private JobTimers $timers,
        private EligibleProsQuery $eligiblePros,
    ) {}

    /**
     * Customer posts a draft (draft → open). Guards: verified phone, a described problem,
     * their own geocoded property, active trade, a valid date, and (once pros are required)
     * an eligible pro within range (spec 020). The job takes the property's point and area name.
     *
     * @throws CannotPostServiceJob
     */
    public function handle(User $customer, ServiceJob $job): ServiceJob
    {
        Gate::forUser($customer)->authorize('update', $job);

        $limitKey = 'post-job:'.$customer->id;

        if (RateLimiter::tooManyAttempts($limitKey, (int) config('sortd.jobs.posts_per_day'))) {
            throw new CannotPostServiceJob(__('You have posted a lot of jobs today. Please try again tomorrow.'));
        }

        $job = DB::transaction(function () use ($customer, $job): ServiceJob {
            $job = ServiceJob::query()->with(['trade', 'property'])->lockForUpdate()->findOrFail($job->id);

            $this->guard($customer, $job);
            $this->settleSummary($job);

            $job->forceFill(['location' => $job->property->location, 'area_label' => $job->property->area_label]);
            $job->posted_at = now();
            $job->quote_window_ends_at = now()->addHours($this->timers->quote_window_hours);

            $this->stateMachine->transition($job, ServiceJobStatus::Open, 'job_posted', ActorType::Customer, $customer->id);

            return $job;
        });

        RateLimiter::hit($limitKey, 24 * 60 * 60);

        SendJobPostedMessage::dispatch($job->id);
        StartMatching::dispatch($job->id);

        return $job;
    }

    /**
     * Keep the description the customer saw: their edit, or an AI summary that
     * still matches the details and passes the rules (spec 007, AC8). Never calls the assistant.
     */
    private function settleSummary(ServiceJob $job): void
    {
        $keep = match ($job->ai_summary_source) {
            SummarySource::CustomerEdited => trim((string) $job->ai_summary) !== '' && mb_strlen((string) $job->ai_summary) <= SummaryRules::MAX_LENGTH,
            SummarySource::Ai => SummaryRules::acceptable($job->ai_summary) && $job->ai_summary_input_hash === JobSummaryInput::hash($job),
            SummarySource::None => false,
        };

        if (! $keep) {
            $job->forceFill(['ai_summary' => null, 'ai_summary_source' => SummarySource::None, 'ai_summary_generated_at' => null]);
        }
    }

    /** @throws CannotPostServiceJob */
    private function guard(User $customer, ServiceJob $job): void
    {
        if ($job->status !== ServiceJobStatus::Draft) {
            throw new CannotPostServiceJob(__('This job has already been posted.'));
        }

        if ($customer->phone_verified_at === null) {
            throw new CannotPostServiceJob(__('Please verify your phone number first.'));
        }

        if (! $job->trade->is_active) {
            throw new CannotPostServiceJob(__('This trade is not available right now.'));
        }

        if (mb_strlen((string) $job->customer_notes) > (int) config('sortd.jobs.notes_max_length')) {
            throw new CannotPostServiceJob(__('Notes can be up to :max characters.', ['max' => config('sortd.jobs.notes_max_length')]));
        }

        if ($job->facts === [] && trim((string) $job->customer_notes) === '') {
            throw new CannotPostServiceJob(__('Please tell us what the problem is.'));
        }

        $property = $job->property;

        if (! $property instanceof Property || $property->trashed() || $property->user_id !== $customer->id || $property->location === null) {
            throw new CannotPostServiceJob(__('Please choose one of your saved addresses.'));
        }

        $date = $job->preferred_date;
        $window = $job->time_window;

        if ($date === null || ! $window instanceof TimeWindow) {
            throw new CannotPostServiceJob(__('Please choose when you need the work done.'));
        }

        $today = LocalTime::today()->toDateString();
        $lastDay = LocalTime::today()->addDays((int) config('sortd.jobs.booking_days_ahead'))->toDateString();

        if ($date->toDateString() < $today || $date->toDateString() > $lastDay) {
            throw new CannotPostServiceJob(__('Please choose a date in the next :days days.', ['days' => config('sortd.jobs.booking_days_ahead')]));
        }

        if ($window === TimeWindow::Today && $date->toDateString() !== $today) {
            throw new CannotPostServiceJob(__('Urgent same-day bookings must be for today.'));
        }

        if (! $this->eligiblePros->covers($job->trade, $property->location, $customer)) {
            throw new NoEligiblePros(__('We don’t have :trade pros near you yet.', ['trade' => mb_strtolower($job->trade->name)]));
        }
    }
}

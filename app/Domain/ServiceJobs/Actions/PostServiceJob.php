<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Actions;

use App\Contracts\Data\MessageReceipt;
use App\Contracts\Data\OutgoingMessage;
use App\Contracts\MessagingChannel;
use App\Domain\ServiceJobs\Enums\ActorType;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\TimeWindow;
use App\Domain\ServiceJobs\Exceptions\CannotPostServiceJob;
use App\Domain\ServiceJobs\ServiceJobStateMachine;
use App\Domain\ServiceJobs\Support\ScopingAnswers;
use App\Models\Property;
use App\Models\ServiceJob;
use App\Models\User;
use App\Settings\JobTimers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

final readonly class PostServiceJob
{
    public function __construct(
        private ServiceJobStateMachine $stateMachine,
        private MessagingChannel $messaging,
        private JobTimers $timers,
    ) {}

    /**
     * Customer posts a draft (draft → open). Guards: verified phone, required
     * answers, their own property in an active suburb, active service, a valid
     * date. The "eligible pro" guard arrives with spec 006 (decision 1).
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
            $job = ServiceJob::query()->with(['service.questions', 'service.trade', 'property.suburb'])->lockForUpdate()->findOrFail($job->id);

            $this->guard($customer, $job);

            $job->posted_at = now();
            $job->quote_window_ends_at = now()->addHours($this->timers->quote_window_hours);

            $this->stateMachine->transition($job, ServiceJobStatus::Open, 'job_posted', ActorType::Customer, $customer->id);

            return $job;
        });

        RateLimiter::hit($limitKey, 24 * 60 * 60);

        DB::afterCommit(fn (): MessageReceipt => $this->messaging->send(new OutgoingMessage(
            (string) $customer->phone_e164,
            'job_posted',
            ['service' => $job->service->name],
        )));

        return $job;
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

        if (! $job->service->is_active || ! $job->service->trade->is_active) {
            throw new CannotPostServiceJob(__('This service is not available right now.'));
        }

        if (ScopingAnswers::missingRequired($job->service, $job->scoping_answers) !== []) {
            throw new CannotPostServiceJob(__('Please answer all the required questions.'));
        }

        $property = $job->property;

        if (! $property instanceof Property || $property->trashed() || $property->user_id !== $customer->id) {
            throw new CannotPostServiceJob(__('Please choose one of your properties.'));
        }

        if (! $property->suburb->is_active) {
            throw new CannotPostServiceJob(__("Sortd isn't in :suburb yet.", ['suburb' => $property->suburb->name]));
        }

        $date = $job->preferred_date;
        $window = $job->time_window;

        if ($date === null || ! $window instanceof TimeWindow) {
            throw new CannotPostServiceJob(__('Please choose when you need the work done.'));
        }

        if ($date->isBefore(today()) || $date->isAfter(today()->addDays((int) config('sortd.jobs.booking_days_ahead')))) {
            throw new CannotPostServiceJob(__('Please choose a date in the next :days days.', ['days' => config('sortd.jobs.booking_days_ahead')]));
        }

        if ($window === TimeWindow::Today && (! $job->service->emergency_capable || ! $date->isToday())) {
            throw new CannotPostServiceJob(__('Urgent same-day bookings are only for emergency services, today.'));
        }
    }
}

<?php

declare(strict_types=1);

use App\Domain\ServiceJobs\Actions\CancelServiceJob;
use App\Domain\ServiceJobs\Actions\PostServiceJob;
use App\Domain\ServiceJobs\Actions\SaveBookingDraft;
use App\Domain\ServiceJobs\Data\BookingData;
use App\Domain\ServiceJobs\Enums\ActorType;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\TimeWindow;
use App\Domain\ServiceJobs\Enums\Urgency;
use App\Domain\ServiceJobs\Exceptions\CannotPostServiceJob;
use App\Domain\ServiceJobs\Exceptions\NoEligiblePros;
use App\Domain\ServiceJobs\Exceptions\TransitionNotAllowed;
use App\Domain\ServiceJobs\ServiceJobStateMachine;
use App\Models\Property;
use App\Models\ServiceJob;
use App\Models\ServiceJobEvent;
use App\Models\User;
use App\Settings\JobTimers;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
    $this->customer = User::factory()->customer()->create();
    $this->property = Property::factory()->for($this->customer)->create();
    $this->plumbing = tradeOf('plumbing');
    proNear(['plumbing']);
});

// --- State machine (AC16) -------------------------------------------------------

it('allows exactly the transitions in the lifecycle map', function (): void {
    $machine = new ServiceJobStateMachine;

    foreach (ServiceJobStatus::cases() as $from) {
        foreach (ServiceJobStatus::cases() as $to) {
            $allowed = in_array($to, ServiceJobStateMachine::transitions()[$from->value], true);
            expect($machine->canTransition($from, $to))->toBe($allowed, "{$from->value} → {$to->value}");
        }
    }

    expect(array_keys(ServiceJobStateMachine::transitions()))->toEqualCanonicalizing(array_map(fn (ServiceJobStatus $s): string => $s->value, ServiceJobStatus::cases()));
});

it('refuses transitions that are not in the map and records nothing', function (): void {
    $job = draftJob($this->customer, $this->plumbing);

    expect(fn (): ServiceJobEvent => (new ServiceJobStateMachine)->transition($job, ServiceJobStatus::Completed, 'x', ActorType::System, null))
        ->toThrow(TransitionNotAllowed::class);

    expect($job->fresh()->status)->toBe(ServiceJobStatus::Draft)->and(ServiceJobEvent::query()->count())->toBe(0);
});

it('never lets job events be changed or deleted', function (): void {
    $job = draftJob($this->customer, $this->plumbing);
    app(PostServiceJob::class)->handle($this->customer, $job);
    $event = ServiceJobEvent::query()->sole();

    expect(fn () => $event->update(['event_type' => 'tampered']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class);
});

// --- Posting (AC9–AC11) ----------------------------------------------------------

it('posts a draft: open, timestamps, one event, message after commit (AC9, AC11)', function (): void {
    $this->freezeTime();
    $job = draftJob($this->customer, $this->plumbing);

    app(PostServiceJob::class)->handle($this->customer, $job);

    $job->refresh();
    expect($job->status)->toBe(ServiceJobStatus::Open)
        ->and($job->posted_at->getTimestamp())->toBe(now()->getTimestamp())
        ->and($job->quote_window_ends_at->getTimestamp())->toBe(now()->addHours(72)->getTimestamp());

    $event = ServiceJobEvent::query()->sole();
    expect($event->from_status)->toBe(ServiceJobStatus::Draft)
        ->and($event->to_status)->toBe(ServiceJobStatus::Open)
        ->and($event->actor_type)->toBe(ActorType::Customer)
        ->and($event->actor_id)->toBe($this->customer->id);

    expect(noticeCount($this->customer, 'job_posted'))->toBe(1);
});

it('uses the quote window from settings', function (): void {
    $this->freezeTime();
    app(JobTimers::class)->fill(['quote_window_hours' => 48])->save();
    $job = draftJob($this->customer, $this->plumbing);

    app(PostServiceJob::class)->handle($this->customer, $job);

    expect($job->fresh()->quote_window_ends_at->getTimestamp())->toBe(now()->addHours(48)->getTimestamp());
});

it('refuses to post when a guard fails and leaves the draft untouched (AC10)', function (Closure $arrange, string $message): void {
    $job = $arrange($this);

    expect(fn () => app(PostServiceJob::class)->handle($this->customer, $job))->toThrow(CannotPostServiceJob::class, $message);

    expect($job->fresh()->status)->toBe(ServiceJobStatus::Draft)->and(ServiceJobEvent::query()->count())->toBe(0);
    expect(allNoticeCount('job_posted'))->toBe(0);
})->with([
    'phone not verified' => [function ($test): ServiceJob {
        $job = draftJob($test->customer, $test->plumbing);
        $test->customer->forceFill(['phone_verified_at' => null])->save();

        return $job;
    }, 'verify your phone'],
    'no problem described' => [fn ($test): ServiceJob => draftJob($test->customer, $test->plumbing, ['facts' => [], 'notes' => null]), 'what the problem is'],
    'no property' => [fn ($test): ServiceJob => draftJob($test->customer, $test->plumbing, ['property' => null]), 'saved addresses'],
    'property without a location' => [function ($test): ServiceJob {
        $job = draftJob($test->customer, $test->plumbing);
        $test->property->forceFill(['location' => null])->save();

        return $job;
    }, 'saved addresses'],
    'deleted property' => [function ($test): ServiceJob {
        $job = draftJob($test->customer, $test->plumbing);
        $test->property->delete();

        return $job;
    }, 'saved addresses'],
    'inactive trade' => [function ($test): ServiceJob {
        $job = draftJob($test->customer, $test->plumbing);
        $test->plumbing->update(['is_active' => false]);

        return $job;
    }, 'not available'],
    'no date' => [fn ($test): ServiceJob => draftJob($test->customer, $test->plumbing, ['date' => null]), 'choose when'],
    'date too far ahead' => [fn ($test): ServiceJob => draftJob($test->customer, $test->plumbing, ['date' => CarbonImmutable::today('Africa/Johannesburg')->addDays(31)]), 'next 30 days'],
    'date in the past' => [fn ($test): ServiceJob => draftJob($test->customer, $test->plumbing, ['date' => CarbonImmutable::today('Africa/Johannesburg')->subDay()]), 'next 30 days'],
    'urgent window on a later day' => [fn ($test): ServiceJob => draftJob($test->customer, $test->plumbing, ['date' => CarbonImmutable::today('Africa/Johannesburg')->addDays(2), 'window' => TimeWindow::Today]), 'must be for today'],
]);

it('posts an urgent same-day job for any trade (spec 020, D-a)', function (): void {
    $electrical = tradeOf('electrical');
    proNear(['electrical']);
    $job = draftJob($this->customer, $electrical, ['date' => CarbonImmutable::today('Africa/Johannesburg'), 'window' => TimeWindow::Today]);

    app(PostServiceJob::class)->handle($this->customer, $job);

    expect($job->fresh()->status)->toBe(ServiceJobStatus::Open)->and($job->fresh()->urgency)->toBe(Urgency::Urgent);
});

it('does not post when no pro of the trade is within range and pros are required', function (): void {
    $job = draftJob($this->customer, tradeOf('painting'));

    expect(fn () => app(PostServiceJob::class)->handle($this->customer, $job))->toThrow(NoEligiblePros::class);
    expect($job->fresh()->status)->toBe(ServiceJobStatus::Draft);
});

it('copies the property point and area onto the job when it is posted', function (): void {
    $job = draftJob($this->customer, $this->plumbing);

    app(PostServiceJob::class)->handle($this->customer, $job);

    $job->refresh();
    expect($job->area_label)->toBe('Musgrave')
        ->and($job->location->getLatitude())->toEqualWithDelta(-29.8587, 0.0001)
        ->and($job->trade_id)->toBe($this->plumbing->id);
});

it("never posts another customer's job or another customer's property", function (): void {
    $job = draftJob($this->customer, $this->plumbing);
    $stranger = User::factory()->customer()->create();

    expect(fn () => app(PostServiceJob::class)->handle($stranger, $job))->toThrow(AuthorizationException::class);

    $theirs = Property::factory()->create();
    $sneaky = draftJob($this->customer, $this->plumbing, ['property' => $theirs->public_id]);
    expect($sneaky->property_id)->toBeNull();
});

it('cannot post the same job twice', function (): void {
    $job = draftJob($this->customer, $this->plumbing);
    app(PostServiceJob::class)->handle($this->customer, $job);

    expect(fn () => app(PostServiceJob::class)->handle($this->customer, $job))->toThrow(CannotPostServiceJob::class, 'already been posted');
    expect(ServiceJobEvent::query()->count())->toBe(1);
});

it('limits posts per day and drafts per customer', function (): void {
    foreach (range(1, 5) as $ignored) {
        draftJob($this->customer, $this->plumbing);
    }

    expect(fn (): ServiceJob => draftJob($this->customer, $this->plumbing))->toThrow(CannotPostServiceJob::class, 'too many unfinished');

    RateLimiter::hit('post-job:'.$this->customer->id, 86400);
    foreach (range(1, 9) as $ignored) {
        RateLimiter::hit('post-job:'.$this->customer->id, 86400);
    }

    expect(fn () => app(PostServiceJob::class)->handle($this->customer, ServiceJob::query()->first()))->toThrow(CannotPostServiceJob::class, 'try again tomorrow');
});

// --- Facts and urgency (AC3, AC5, rules) -------------------------------------------

it('stores the facts Siya extracted, with ids, on the draft', function (): void {
    $job = draftJob($this->customer, $this->plumbing, ['facts' => [['id' => 'f1', 'text' => 'tap drips when fully closed', 'turn' => 1], ['id' => 'f2', 'text' => 'worse since Monday', 'turn' => 2]]]);

    expect($job->fresh()->facts)->toHaveCount(2)->and($job->fresh()->factTexts())->toBe(['tap drips when fully closed', 'worse since Monday']);
});

it('marks a job urgent when Siya flags it or the today window is chosen (AC5, AC7)', function (): void {
    $flagged = draftJob($this->customer, $this->plumbing, ['urgent' => true]);
    $today = draftJob($this->customer, $this->plumbing, ['date' => CarbonImmutable::today('Africa/Johannesburg'), 'window' => TimeWindow::Today]);
    $normal = draftJob($this->customer, $this->plumbing);

    expect($flagged->urgency)->toBe(Urgency::Urgent)->and($today->urgency)->toBe(Urgency::Urgent)->and($normal->urgency)->toBe(Urgency::Normal);
});

it('keeps the same draft and its photos when the trade is corrected', function (): void {
    $job = draftJob($this->customer, $this->plumbing);

    $corrected = app(SaveBookingDraft::class)->handle($this->customer, tradeOf('electrical'), $job, new BookingData(facts: $job->facts));

    expect($corrected->id)->toBe($job->id)->and($corrected->trade_id)->toBe(tradeOf('electrical')->id);
});

// --- Stale drafts (AC12) -----------------------------------------------------------

it('cancels drafts untouched for 7 days, safely when run twice', function (): void {
    $old = draftJob($this->customer, $this->plumbing);
    $recent = draftJob($this->customer, $this->plumbing);
    $posted = draftJob($this->customer, $this->plumbing);
    app(PostServiceJob::class)->handle($this->customer, $posted);
    ServiceJob::query()->whereKey([$old->id, $posted->id])->update(['updated_at' => now()->subDays(8)]);

    $this->artisan('getsorted:cancel-stale-drafts')->assertSuccessful();
    $this->artisan('getsorted:cancel-stale-drafts')->assertSuccessful();

    expect($old->fresh()->status)->toBe(ServiceJobStatus::Cancelled)
        ->and($recent->fresh()->status)->toBe(ServiceJobStatus::Draft)
        ->and($posted->fresh()->status)->toBe(ServiceJobStatus::Open)
        ->and(ServiceJobEvent::query()->where('service_job_id', $old->id)->sole())
        ->actor_type->toBe(ActorType::System)
        ->to_status->toBe(ServiceJobStatus::Cancelled);
});

it('refuses notes over the limit when saving a draft', function (): void {
    expect(fn (): ServiceJob => draftJob($this->customer, $this->plumbing, ['notes' => str_repeat('x', 1001)]))->toThrow(CannotPostServiceJob::class, 'up to 1000 characters');
});

it('does not expire a draft the customer touched after it was picked', function (): void {
    $job = draftJob($this->customer, $this->plumbing);

    $cancelled = app(CancelServiceJob::class)->handle($job, ActorType::System, null, 'Draft expired', now()->subDays(7)->toImmutable());

    expect($cancelled)->toBeNull()->and($job->fresh()->status)->toBe(ServiceJobStatus::Draft);
});

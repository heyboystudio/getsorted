<?php

declare(strict_types=1);

use App\Domain\Quotes\Actions\AcceptQuote;
use App\Domain\ServiceJobs\Actions\CancelBookedJob;
use App\Domain\ServiceJobs\Actions\MarkJobDone;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Exceptions\CannotCancelJob;
use App\Domain\ServiceJobs\Exceptions\CannotFinishJob;
use App\Domain\ServiceJobs\ServiceJobStateMachine;
use App\Domain\ServiceJobs\Support\JobTimeline;
use App\Livewire\Account\Jobs\Show as CustomerJob;
use App\Livewire\Pros\Jobs\Show as ProJob;
use App\Models\Pro;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\ServiceJobEvent;
use App\Models\ServiceJobInvite;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/** Spec 024: finishing or cancelling a booked job, with no payments through GetSorted. */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
});

/** A job the client has booked with a pro, through the real acceptance. */
function bookedJob(): array
{
    $job = ServiceJob::factory()->open()->create();
    $quote = Quote::factory()->create(['service_job_id' => $job->id]);
    app(AcceptQuote::class)->handle($job->customer, $quote);

    return [$job->refresh(), $quote->pro, $job->customer];
}

// --- Done (AC1–AC3) ----------------------------------------------------------------------------

it('books the job on acceptance and lets the lifecycle go from booked straight to done', function (): void {
    [$job] = bookedJob();

    expect($job->status)->toBe(ServiceJobStatus::Scheduled)
        ->and(app(ServiceJobStateMachine::class)->canTransition(ServiceJobStatus::Scheduled, ServiceJobStatus::Completed))->toBeTrue();
});

it('lets the client mark a booked job done, tells the pro and records who did it', function (): void {
    [$job, $pro, $customer] = bookedJob();

    app(MarkJobDone::class)->handle($customer, $job);

    $job->refresh();
    expect($job->status)->toBe(ServiceJobStatus::Completed)->and($job->completed_by)->toBe('customer')->and($job->completed_at)->not->toBeNull()
        ->and(noticeCount($pro->user, 'job_done'))->toBe(1)->and(noticeCount($customer, 'job_done'))->toBe(0)
        ->and(ServiceJobEvent::query()->where('event_type', 'job_done')->sole()->actor_id)->toBe($customer->id);
});

it('lets the chosen pro mark it done and tells the client', function (): void {
    [$job, $pro, $customer] = bookedJob();

    app(MarkJobDone::class)->handle($pro->user, $job);

    expect($job->refresh()->completed_by)->toBe('pro')->and(noticeCount($customer, 'job_done'))->toBe(1);
});

it('refuses outsiders, other pros, jobs that are not booked and jobs already done', function (): void {
    [$job, $pro, $customer] = bookedJob();
    $stranger = User::factory()->customer()->create();
    $otherPro = Pro::factory()->approved()->create();

    expect(fn () => app(MarkJobDone::class)->handle($stranger, $job))->toThrow(CannotFinishJob::class)
        ->and(fn () => app(MarkJobDone::class)->handle($otherPro->user, $job))->toThrow(CannotFinishJob::class);

    app(MarkJobDone::class)->handle($customer, $job);
    expect(fn () => app(MarkJobDone::class)->handle($customer, $job->refresh()))->toThrow(CannotFinishJob::class);

    $open = ServiceJob::factory()->open()->create();
    expect(fn () => app(MarkJobDone::class)->handle($open->customer, $open))->toThrow(CannotFinishJob::class);
});

// --- Cancelling after booking (AC4–AC7) -----------------------------------------------------------

it('lets the client cancel a booking with a reason and tells the pro', function (): void {
    [$job, $pro, $customer] = bookedJob();

    app(CancelBookedJob::class)->handle($customer, $job, 'Found someone I know');

    $job->refresh();
    expect($job->status)->toBe(ServiceJobStatus::Cancelled)->and($job->cancelled_by)->toBe('customer')->and($job->cancel_reason)->toBe('Found someone I know')
        ->and(noticeCount($pro->user, 'booking_cancelled'))->toBe(1)
        ->and(ServiceJobEvent::query()->where('event_type', 'booking_cancelled_by_customer')->exists())->toBeTrue();
});

it('lets the pro cancel with a reason and tells the client how to choose again', function (): void {
    [$job, $pro, $customer] = bookedJob();

    app(CancelBookedJob::class)->handle($pro->user, $job, 'Van broke down');

    $notice = $customer->notifications()->get()->first(fn ($n): bool => ($n->data['kind'] ?? '') === 'booking_cancelled');
    expect($job->refresh()->cancelled_by)->toBe('pro')->and($notice->data['body'])->toContain('Van broke down')->and($notice->data['url'])->toContain('/book/');
});

it('needs a real reason and refuses outsiders and finished jobs', function (): void {
    [$job, $pro, $customer] = bookedJob();

    expect(fn () => app(CancelBookedJob::class)->handle($customer, $job, '  '))->toThrow(CannotCancelJob::class)
        ->and(fn () => app(CancelBookedJob::class)->handle($customer, $job, str_repeat('a', 301)))->toThrow(CannotCancelJob::class)
        ->and(fn () => app(CancelBookedJob::class)->handle(User::factory()->customer()->create(), $job, 'Not mine'))->toThrow(CannotCancelJob::class);

    app(MarkJobDone::class)->handle($customer, $job);
    expect(fn () => app(CancelBookedJob::class)->handle($customer, $job->refresh(), 'Too late'))->toThrow(CannotCancelJob::class);
});

it('shows the client the new events on their timeline', function (): void {
    [$job, , $customer] = bookedJob();
    app(MarkJobDone::class)->handle($customer, $job);

    expect(JobTimeline::forJob($job)->pluck('text')->all())->toContain('The job was marked done');
});

// --- Screens (AC9–AC10) -----------------------------------------------------------------------

it('lets the client mark done or cancel from their job page', function (): void {
    [$job, , $customer] = bookedJob();
    $this->actingAs($customer);

    Livewire::test(CustomerJob::class, ['job' => $job])
        ->assertSee('Mark as done')->assertSee('Cancel this booking')
        ->call('confirmBookedCancel')->set('bookedCancelReason', 'x')->call('cancelBooked')->assertHasErrors('bookedCancelReason')
        ->call('keepBooking')->call('confirmDone')->assertSee('Is the work finished?')->call('markDone')->assertHasNoErrors()
        ->assertSee('This job is done')->assertDontSee('Mark as done');

    expect($job->refresh()->status)->toBe(ServiceJobStatus::Completed);
});

it('lets the client cancel a booking from their job page', function (): void {
    [$job, , $customer] = bookedJob();
    $this->actingAs($customer);

    Livewire::test(CustomerJob::class, ['job' => $job])
        ->call('confirmBookedCancel')->set('bookedCancelReason', 'Plans changed')->call('cancelBooked')->assertHasNoErrors()->assertSee('This job was cancelled');

    expect($job->refresh()->status)->toBe(ServiceJobStatus::Cancelled);
});

it('lets the chosen pro mark done or cancel from their job page', function (): void {
    [$job, $pro] = bookedJob();
    $invite = ServiceJobInvite::factory()->create(['service_job_id' => $job->id, 'pro_id' => $pro->id, 'status' => 'quoted']);
    $this->actingAs($pro->user);

    Livewire::test(ProJob::class, ['invite' => $invite])
        ->assertSee('Mark as done')->assertSee('Cancel this booking')
        ->call('confirmDone')->call('markDone')->assertHasNoErrors()->assertSee('This job is done');

    expect($job->refresh()->status)->toBe(ServiceJobStatus::Completed)->and($job->completed_by)->toBe('pro');
});

// --- The nudge (AC8) ----------------------------------------------------------------------------

it('asks both sides once, three days after the start date, whether the job is done', function (): void {
    [$job, $pro, $customer] = bookedJob();
    $fresh = bookedJob();
    $job->forceFill(['scheduled_for' => now()->subDays(4)->toDateString()])->save();
    $fresh[0]->forceFill(['scheduled_for' => now()->subDay()->toDateString()])->save();

    $this->artisan('getsorted:nudge-finished-jobs')->assertSuccessful();
    $this->artisan('getsorted:nudge-finished-jobs')->assertSuccessful();

    expect(noticeCount($customer, 'job_finish_check'))->toBe(1)->and(noticeCount($pro->user, 'job_finish_check'))->toBe(1)
        ->and(noticeCount($fresh[2], 'job_finish_check'))->toBe(0)->and($job->refresh()->finish_nudged_at)->not->toBeNull()
        ->and($job->status)->toBe(ServiceJobStatus::Scheduled);
});

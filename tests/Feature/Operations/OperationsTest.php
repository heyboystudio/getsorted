<?php

declare(strict_types=1);

use App\Domain\Accounts\Enums\Role;
use App\Domain\Operations\Support\StalledJobs;
use App\Domain\Operations\Support\SuccessMeasures;
use App\Domain\Quotes\Actions\AcceptQuote;
use App\Domain\Reviews\Actions\SubmitReview;
use App\Domain\ServiceJobs\Actions\MarkJobDone;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\Urgency;
use App\Filament\Admin\Widgets\AdminStats;
use App\Filament\Admin\Widgets\StalledJobsList;
use App\Filament\Admin\Widgets\SuccessMeasuresStats;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/** Spec 027: stalled jobs and the PRD's success measures on the admin dashboard. */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
});

function postedJobAgo(int $hours, array $attributes = []): ServiceJob
{
    $job = ServiceJob::factory()->open()->create($attributes);
    $job->forceFill(['posted_at' => now()->subHours($hours), 'quotes_count' => 0])->save();

    return $job;
}

// --- Stalled jobs (AC1–AC3) ---------------------------------------------------------------------

it('flags an open job with no estimate after four hours, sooner when it is urgent', function (): void {
    $quiet = postedJobAgo(5);
    postedJobAgo(2);
    $urgent = postedJobAgo(2, ['urgency' => Urgency::Urgent]);
    postedJobAgo(0, ['urgency' => Urgency::Urgent]);

    $stalled = StalledJobs::all();

    expect($stalled->pluck('job.id')->sort()->values()->all())->toBe(collect([$quiet->id, $urgent->id])->sort()->values()->all())
        ->and($stalled->pluck('reason')->unique()->all())->toBe([StalledJobs::NO_ESTIMATE]);
});

it('does not flag an open job once it has an estimate', function (): void {
    $job = postedJobAgo(10);
    Quote::factory()->create(['service_job_id' => $job->id]);
    $job->forceFill(['quotes_count' => 1])->save();

    expect(StalledJobs::all())->toHaveCount(0);
});

it('flags a booked job three days past its start date that nobody marked done', function (): void {
    $job = ServiceJob::factory()->open()->create();
    $quote = Quote::factory()->create(['service_job_id' => $job->id]);
    app(AcceptQuote::class)->handle($job->customer, $quote);
    $job->refresh()->forceFill(['scheduled_for' => now()->subDays(4)->toDateString()])->save();

    $recent = ServiceJob::factory()->open()->create();
    app(AcceptQuote::class)->handle($recent->customer, Quote::factory()->create(['service_job_id' => $recent->id]));
    $recent->refresh()->forceFill(['scheduled_for' => now()->subDay()->toDateString()])->save();

    expect(StalledJobs::all()->pluck('job.id')->all())->toBe([$job->id])->and(StalledJobs::all()->first()['reason'])->toBe(StalledJobs::NOT_FINISHED);

    app(MarkJobDone::class)->handle($job->customer, $job->refresh());
    expect(StalledJobs::all())->toHaveCount(0);
});

// --- Success measures (AC4–AC5) -----------------------------------------------------------------

it('shows nothing invented when there are no jobs', function (): void {
    foreach (SuccessMeasures::over(90) as $measure) {
        expect($measure['value'])->toBeNull()->and($measure['display'])->toBe('—')->and($measure['met'])->toBeNull();
    }
});

it('measures time to first estimate, two-estimate jobs, acceptance, done and reviewed', function (): void {
    // Job A: first estimate after 2 h, two estimates, chosen, done, reviewed.
    $a = postedJobAgo(48);
    $first = Quote::factory()->create(['service_job_id' => $a->id, 'submitted_at' => $a->posted_at->addHours(2)]);
    Quote::factory()->create(['service_job_id' => $a->id, 'submitted_at' => $a->posted_at->addHours(5)]);
    app(AcceptQuote::class)->handle($a->customer, $first);
    app(MarkJobDone::class)->handle($a->customer, $a->refresh());
    app(SubmitReview::class)->handle($a->customer, $a->refresh(), 5, null);

    // Job B: first estimate after 6 h, one estimate, not chosen.
    $b = postedJobAgo(48);
    Quote::factory()->create(['service_job_id' => $b->id, 'submitted_at' => $b->posted_at->addHours(6)]);

    // Job C: nothing yet.
    postedJobAgo(1);

    $m = SuccessMeasures::over(90);

    expect($m['first_quote']['value'])->toBe(4.0)->and($m['first_quote']['met'])->toBeFalse()
        ->and($m['two_quotes']['value'])->toBe(33.3)->and($m['two_quotes']['sample'])->toBe(3)
        ->and($m['accept']['value'])->toBe(50.0)->and($m['accept']['met'])->toBeTrue()
        ->and($m['done']['value'])->toBe(100.0)->and($m['reviewed']['value'])->toBe(100.0);
});

it('leaves out jobs posted before the window', function (): void {
    postedJobAgo(24 * 100);

    expect(SuccessMeasures::over(90)['two_quotes']['sample'])->toBe(0);
});

// --- Dashboard (AC6) ----------------------------------------------------------------------------

it('shows the stalled jobs and the success measures on the admin dashboard', function (): void {
    postedJobAgo(6);
    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminSupport->value);
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');

    Livewire::test(StalledJobsList::class)->assertSee('Jobs that need a nudge')->assertSee('No estimate after 4 h');
    Livewire::test(SuccessMeasuresStats::class)->assertSee('Median time to first estimate')->assertSee('Done jobs reviewed');
    Livewire::test(AdminStats::class)->assertSee('Stalled jobs')->assertDontSee('Awaiting deposit');
    expect(ServiceJobStatus::Open->value)->toBe('open');
});

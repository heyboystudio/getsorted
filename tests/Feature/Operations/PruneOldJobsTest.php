<?php

declare(strict_types=1);

use App\Domain\Operations\Actions\PruneOldJobs;
use App\Domain\Quotes\Actions\AcceptQuote;
use App\Domain\ServiceJobs\Actions\MarkJobDone;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Models\Introduction;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\ServiceJob;
use App\Models\ServiceJobEvent;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/** POPIA retention: photos and personal content go 24 months after a job ends (privacy notice). */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
});

function finishedJobWithMoneyRecord(): ServiceJob
{
    $job = ServiceJob::factory()->open()->create(['customer_notes' => 'Dog is friendly, key under the pot', 'facts' => [['id' => 'f1', 'text' => 'tap drips', 'turn' => 1]]]);
    $quote = Quote::factory()->create(['service_job_id' => $job->id, 'notes' => 'Call me after 5']);
    (new QuoteLine)->forceFill(['quote_id' => $quote->id, 'kind' => 'labour', 'description' => 'Fix the tap at Mrs Naidoo', 'quantity' => 1, 'unit_price_cents' => 45000, 'line_total_cents' => 45000, 'sort' => 0])->save();
    app(AcceptQuote::class)->handle($job->customer, $quote);
    app(MarkJobDone::class)->handle($job->customer, $job->refresh());

    return $job->refresh();
}

it('scrubs an old finished job that has a money record and keeps its accounting trail', function (): void {
    $job = finishedJobWithMoneyRecord();
    $this->travel(25)->months();

    $result = app(PruneOldJobs::class)->handle();

    $job->refresh();
    expect($result)->toBe(['deleted' => 0, 'scrubbed' => 1])
        ->and($job->customer_notes)->toBeNull()->and($job->facts)->toBe([])->and($job->location)->toBeNull()->and($job->scrubbed_at)->not->toBeNull()
        ->and($job->status)->toBe(ServiceJobStatus::Completed)
        ->and(Quote::query()->sole()->notes)->toBeNull()->and(Quote::query()->sole()->total_cents)->toBe(57050)
        ->and(QuoteLine::query()->sole()->description)->toBe('Removed')->and(QuoteLine::query()->sole()->line_total_cents)->toBe(45000)
        ->and(Introduction::query()->count())->toBe(1)
        ->and(ServiceJobEvent::query()->where('service_job_id', $job->id)->count())->toBeGreaterThan(0)
        ->and(DB::table('service_job_events')->where('service_job_id', $job->id)->where('payload', '!=', '{}')->count())->toBe(0);
});

it('deletes an old cancelled job that never led to an introduction', function (): void {
    $job = ServiceJob::factory()->open()->create();
    $job->forceFill(['status' => ServiceJobStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => 'Changed my mind about the geyser'])->save();
    $this->travel(25)->months();

    $result = app(PruneOldJobs::class)->handle();

    expect($result)->toBe(['deleted' => 1, 'scrubbed' => 0])->and(ServiceJob::query()->count())->toBe(0);
});

it('leaves recent jobs, open jobs and already scrubbed jobs alone, and is safe to repeat', function (): void {
    $recent = finishedJobWithMoneyRecord();
    $open = ServiceJob::factory()->open()->create();
    $this->travel(23)->months();

    expect(app(PruneOldJobs::class)->handle())->toBe(['deleted' => 0, 'scrubbed' => 0]);

    $this->travel(3)->months();
    expect(app(PruneOldJobs::class)->handle())->toBe(['deleted' => 0, 'scrubbed' => 1])
        ->and(app(PruneOldJobs::class)->handle())->toBe(['deleted' => 0, 'scrubbed' => 0])
        ->and($open->refresh()->status)->toBe(ServiceJobStatus::Open)->and($recent->refresh()->scrubbed_at)->not->toBeNull();
});

it('runs from the scheduler command', function (): void {
    $this->artisan('getsorted:prune-old-jobs')->assertSuccessful()->expectsOutputToContain('Deleted 0 job(s)');
});

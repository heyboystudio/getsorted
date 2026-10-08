<?php

declare(strict_types=1);

use App\Domain\Quotes\Actions\AcceptQuote;
use App\Models\Introduction;
use App\Models\Pro;
use App\Models\ProCreditEntry;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
});

it('shows what it would remove and removes nothing without --force', function (): void {
    ServiceJob::factory()->open()->create();

    $this->artisan('getsorted:purge-test-data')->assertSuccessful()->expectsOutputToContain('Dry run');

    expect(ServiceJob::query()->count())->toBe(1);
});

it('removes test jobs and their records but keeps accounts, pros and bought credit', function (): void {
    $job = ServiceJob::factory()->open()->create();
    $quote = Quote::factory()->create(['service_job_id' => $job->id]);
    app(AcceptQuote::class)->handle($job->customer, $quote);
    (new ProCreditEntry)->forceFill(['pro_id' => $quote->pro_id, 'type' => 'purchase', 'amount_cents' => 29_700, 'idempotency_key' => 'purchase:test', 'note' => 'PayFast payment'])->save();
    $users = User::query()->count();

    $this->artisan('getsorted:purge-test-data', ['--force' => true])->assertSuccessful();

    expect(ServiceJob::query()->count())->toBe(0)->and(Quote::query()->count())->toBe(0)->and(Introduction::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe($users)->and(Pro::query()->count())->toBe(1)
        ->and((int) ProCreditEntry::query()->sum('amount_cents'))->toBe(29_700);
});

it('refuses to run in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('getsorted:purge-test-data', ['--force' => true])->assertFailed();
});

it('finds test accounts and jobs on a database that should be clean', function (): void {
    $this->artisan('getsorted:check-for-test-data')->assertSuccessful();

    User::factory()->customer()->create(['email' => 'someone@example.com']);
    ServiceJob::factory()->open()->create();

    $this->artisan('getsorted:check-for-test-data')->assertFailed();
});

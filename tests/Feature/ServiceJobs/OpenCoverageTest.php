<?php

declare(strict_types=1);

use App\Domain\Matching\EligibleProsQuery;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Decision 044 (spec 020): before launch, a customer can book anywhere, even with no pros signed up. */
beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
    config()->set('sortd.coverage.require_pros', false);
    $this->plumbing = tradeOf('plumbing');
});

it('lets customers book with no pros signed up while pros are not required', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    describeJob(threadFor($this->plumbing), $this->plumbing)->call('selectProperty', $property->public_id)
        ->assertSet('stage', 'when');
});

it('still refuses inactive trades', function (): void {
    $query = app(EligibleProsQuery::class);

    expect($query->covers($this->plumbing, durban()))->toBeTrue();

    $this->plumbing->update(['is_active' => false]);
    expect($query->covers($this->plumbing->refresh(), durban()))->toBeFalse();
});

it('requires a pro of the trade within range again when switched back on', function (): void {
    config()->set('sortd.coverage.require_pros', true);

    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    describeJob(threadFor($this->plumbing), $this->plumbing)->call('selectProperty', $property->public_id)
        ->assertSet('stage', 'waitlist');
});

it('lets the customer carry on when a pro is within range and pros are required', function (): void {
    config()->set('sortd.coverage.require_pros', true);
    proNear(['plumbing'], 5);

    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    describeJob(threadFor($this->plumbing), $this->plumbing)->call('selectProperty', $property->public_id)
        ->assertSet('stage', 'when');
});

it('does not count a pro beyond their radius and the soft edge', function (): void {
    config()->set('sortd.coverage.require_pros', true);
    proNear(['plumbing'], 25);

    expect(app(EligibleProsQuery::class)->covers($this->plumbing, durban()))->toBeFalse();
});

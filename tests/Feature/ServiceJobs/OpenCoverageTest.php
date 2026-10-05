<?php

declare(strict_types=1);

use App\Domain\Matching\EligibleProsQuery;
use App\Models\Service;
use App\Models\Suburb;
use App\Models\Trade;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\SuburbSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Decision 044: before launch, every eThekwini suburb takes requests even with no pros. */
beforeEach(function (): void {
    $this->seed([CatalogueSeeder::class, SuburbSeeder::class]);
    config()->set('sortd.coverage.require_pros', false);
    $this->plumbing = Trade::query()->where('key', 'plumbing')->sole();
    $this->leak = Service::query()->where('key', 'leak_repair')->sole();
});

it('lets customers book in any Durban suburb, active or not, with no pros signed up', function (string $slug): void {
    [$customer, $property] = bookingCustomer($slug);
    $this->actingAs($customer);

    describeJob(threadFor($this->leak), $this->leak)->call('selectProperty', $property->public_id)
        ->assertSet('stage', 'when');
})->with(['morningside', 'pinetown']);

it('still refuses inactive services and suburbs outside eThekwini', function (): void {
    $suburb = Suburb::query()->where('slug', 'morningside')->sole();
    $query = app(EligibleProsQuery::class);

    expect($query->covers($this->leak, $suburb))->toBeTrue();

    $this->leak->update(['is_active' => false]);
    expect($query->covers($this->leak->refresh(), $suburb))->toBeFalse();

    $this->leak->update(['is_active' => true]);
    $suburb->update(['municipality' => 'Msunduzi']);
    expect($query->covers($this->leak->refresh(), $suburb->refresh()))->toBeFalse();
});

it('requires an eligible pro again when switched back on', function (): void {
    config()->set('sortd.coverage.require_pros', true);

    [$customer, $property] = bookingCustomer('morningside');
    $this->actingAs($customer);

    describeJob(threadFor($this->leak), $this->leak)->call('selectProperty', $property->public_id)
        ->assertSet('stage', 'waitlist');
});

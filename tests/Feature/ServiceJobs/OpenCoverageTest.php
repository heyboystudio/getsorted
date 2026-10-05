<?php

declare(strict_types=1);

use App\Domain\Matching\EligibleProsQuery;
use App\Livewire\Booking\Wizard;
use App\Models\Service;
use App\Models\Suburb;
use App\Models\Trade;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\SuburbSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Decision 044: before launch, every eThekwini suburb takes requests even with no pros. */
beforeEach(function (): void {
    $this->seed([CatalogueSeeder::class, SuburbSeeder::class]);
    config()->set('sortd.coverage.require_pros', false);
    $this->plumbing = Trade::query()->where('key', 'plumbing')->sole();
    $this->leak = Service::query()->where('key', 'leak_repair')->sole();
});

it('lets customers book in any Durban suburb, active or not, with no pros signed up', function (string $slug): void {
    Livewire::test(Wizard::class, ['trade' => $this->plumbing, 'service' => $this->leak])
        ->call('selectSuburb', $slug)->call('next')
        ->assertSet('step', 'questions');
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

    Livewire::test(Wizard::class, ['trade' => $this->plumbing, 'service' => $this->leak])
        ->call('selectSuburb', 'morningside')->call('next')
        ->assertSet('step', 'waitlist');
});

<?php

declare(strict_types=1);

use App\Domain\Accounts\Enums\Role;
use App\Domain\Properties\Enums\Region;
use App\Domain\Properties\Queries\SuburbSearchQuery;
use App\Filament\Admin\Resources\Suburbs\Pages\CreateSuburb;
use App\Filament\Admin\Resources\Suburbs\Pages\EditSuburb;
use App\Filament\Admin\Resources\Suburbs\Pages\ListSuburbs;
use App\Models\Property;
use App\Models\Suburb;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Database\PostgisFunctions\ST;
use Database\Seeders\SuburbSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SuburbSeeder::class);
});

function suburb(string $slug): Suburb
{
    return Suburb::query()->where('slug', $slug)->sole();
}

it('seeds the launch-area suburbs with centre points (AC1)', function (): void {
    expect(Suburb::query()->count())->toBeGreaterThan(80)
        ->and(Suburb::query()->pluck('municipality')->unique()->all())->toBe(['eThekwini'])
        ->and(suburb('morningside')->region)->toBe(Region::BereaCentral)
        ->and(suburb('morningside')->centroid)->toBeInstanceOf(Point::class)
        ->and(suburb('morningside')->boundary)->toBeNull();

    // Centre points are real map positions: Morningside to Umhlanga is roughly 12 km.
    $metres = (float) Suburb::query()->where('slug', 'morningside')
        ->select(ST::distance(suburb('umhlanga')->centroid, 'centroid')->as('metres'))->value('metres');
    expect($metres)->toBeGreaterThan(9_000)->toBeLessThan(16_000);
});

it('switches on every Durban suburb, from Pinetown to Umhlanga to the Bluff and Toti (decision 044)', function (): void {
    expect(Suburb::query()->where('is_active', false)->count())->toBe(0)
        ->and(Suburb::query()->whereIn('slug', ['pinetown', 'umhlanga', 'bluff', 'amanzimtoti', 'chatsworth', 'hillcrest', 'tongaat'])->count())->toBe(7);
});

it('only adds new suburbs when seeded again (AC3)', function (): void {
    suburb('westville')->update(['is_active' => true, 'name' => 'Westville (edited)']);
    suburb('morningside')->delete();

    $this->seed(SuburbSeeder::class);

    expect(suburb('westville'))->is_active->toBeTrue()->name->toBe('Westville (edited)')
        ->and(Suburb::query()->where('slug', 'morningside')->exists())->toBeTrue();
});

it('finds suburbs from the start of words, active ones first', function (): void {
    $results = app(SuburbSearchQuery::class)->handle('morn');
    expect($results->pluck('slug')->all())->toBe(['morningside']);

    expect(app(SuburbSearchQuery::class)->handle('NORTH')->pluck('slug')->all())->toBe(['durban_north', 'westville_north'])
        ->and(app(SuburbSearchQuery::class)->handle('orth')->all())->toBe([])
        ->and(app(SuburbSearchQuery::class)->handle('')->all())->toBe([]);

    suburb('bluff')->update(['is_active' => false]);
    $mixed = app(SuburbSearchQuery::class)->handle('bl');
    expect($mixed->pluck('slug')->all())->toBe(['bluff'])->and($mixed->last()->is_active)->toBeFalse();
});

it('lets editors manage suburbs in the admin panel (AC4)', function (): void {
    Filament::setCurrentPanel('admin');
    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminSupport->value);
    $this->actingAs($admin);

    Livewire::test(ListSuburbs::class)->searchTable('Westville')->assertCanSeeTableRecords(Suburb::query()->where('name', 'like', 'Westville%')->get());

    Livewire::test(EditSuburb::class, ['record' => 'westville'])
        ->fillForm(['is_active' => true, 'name' => 'Westville'])
        ->call('save')->assertHasNoFormErrors();
    expect(suburb('westville')->is_active)->toBeTrue();

    Livewire::test(CreateSuburb::class)
        ->fillForm(['name' => 'Cliffdale', 'slug' => 'cliffdale', 'region' => Region::West->value, 'latitude' => -29.78, 'longitude' => 30.76, 'is_active' => false])
        ->call('create')->assertHasNoFormErrors();
    expect(suburb('cliffdale')->centroid->getLatitude())->toEqualWithDelta(-29.78, 0.0001);

    Livewire::test(EditSuburb::class, ['record' => 'westville'])->assertActionDoesNotExist('delete');
    expect($admin->can('delete', suburb('westville')))->toBeFalse();
});

it('gives vetting and finance admins read-only suburbs (AC4)', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminVetting->value);

    expect($admin->can('viewAny', Suburb::class))->toBeTrue()
        ->and($admin->can('update', suburb('morningside')))->toBeFalse()
        ->and($admin->can('create', Suburb::class))->toBeFalse();
});

it('shows how many properties each suburb has (AC4)', function (): void {
    Filament::setCurrentPanel('admin');
    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminSuper->value);
    $this->actingAs($admin);
    Property::factory()->count(2)->for(suburb('glenwood'))->create();

    Livewire::test(ListSuburbs::class)->assertTableColumnStateSet('properties_count', 2, suburb('glenwood'));
});

it('rejects a duplicate suburb name in the admin form', function (): void {
    Filament::setCurrentPanel('admin');
    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminSuper->value);
    $this->actingAs($admin);

    Livewire::test(CreateSuburb::class)
        ->fillForm(['name' => 'Morningside', 'slug' => 'morningside_two', 'region' => Region::BereaCentral->value, 'latitude' => -29.82, 'longitude' => 31.0, 'is_active' => false])
        ->call('create')->assertHasFormErrors(['name']);
});

it('does not trip over an admin-added suburb with the same name when seeding', function (): void {
    suburb('kloof')->delete();
    Suburb::factory()->create(['slug' => 'kloof_area', 'name' => 'Kloof', 'municipality' => 'eThekwini']);

    $this->seed(SuburbSeeder::class);

    expect(Suburb::query()->where('name', 'Kloof')->count())->toBe(1);
});

it('audit-logs moving a suburb centre', function (): void {
    Filament::setCurrentPanel('admin');
    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminSuper->value);
    $this->actingAs($admin);

    Livewire::test(EditSuburb::class, ['record' => 'glenwood'])
        ->fillForm(['latitude' => -29.8710, 'longitude' => 30.9960])->call('save')->assertHasNoFormErrors();

    $entry = Activity::query()->where('description', 'suburb centre moved')->sole();
    expect($entry->causer_id)->toBe($admin->id)
        ->and($entry->properties['attributes']['latitude'])->toEqualWithDelta(-29.8710, 0.0001)
        ->and($entry->properties['old']['latitude'])->toEqualWithDelta(-29.8700, 0.0001);
});

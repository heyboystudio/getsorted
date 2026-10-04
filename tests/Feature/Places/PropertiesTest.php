<?php

declare(strict_types=1);

use App\Domain\Properties\Enums\PropertyType;
use App\Livewire\Account\Properties\Form;
use App\Livewire\Account\Properties\Index;
use App\Models\Property;
use App\Models\Suburb;
use App\Models\User;
use Database\Seeders\SuburbSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SuburbSeeder::class);
    $this->customer = User::factory()->customer()->create();
    $this->actingAs($this->customer);
});

function addProperty(array $fields = []): Testable
{
    return Livewire::test(Form::class)
        ->set('label', $fields['label'] ?? 'Home')
        ->set('streetAddress', $fields['streetAddress'] ?? '12 Innes Road')
        ->call('selectSuburb', $fields['suburb'] ?? 'morningside')
        ->set('postalCode', $fields['postalCode'] ?? '4001')
        ->set('propertyType', $fields['propertyType'] ?? PropertyType::House->value)
        ->call('save');
}

it('lists my properties, with an empty state (AC5)', function (): void {
    Livewire::test(Index::class)->assertSee('No saved properties yet')->assertSee('Add property');

    Property::factory()->for($this->customer)->create(['label' => 'Beach flat']);

    Livewire::test(Index::class)->assertSee('Beach flat')->assertDontSee('No saved properties yet');
    $this->get('/app/properties')->assertOk();
    $this->get('/app')->assertSee('Saved properties');
});

it('adds a property linked to its suburb with the suburb centre as location (AC6, AC7)', function (): void {
    addProperty()->assertHasNoErrors()->assertRedirect(route('properties.index'));

    $property = Property::query()->sole();
    $suburb = Suburb::query()->where('slug', 'morningside')->sole();

    expect($property->user_id)->toBe($this->customer->id)
        ->and($property->label)->toBe('Home')
        ->and($property->street_address)->toBe('12 Innes Road')
        ->and($property->suburb_id)->toBe($suburb->id)
        ->and($property->property_type)->toBe(PropertyType::House)
        ->and($property->public_id)->toHaveLength(26)
        ->and($property->location->getLatitude())->toEqualWithDelta($suburb->centroid->getLatitude(), 0.000001);
});

it('shows the privacy note and suggests suburbs as you type (AC6)', function (): void {
    Livewire::test(Form::class)
        ->assertSee('We only share your street address with the pro you choose')
        ->set('suburbQuery', 'west')
        ->assertSee('Westville')
        ->assertSee('Coming soon');
});

it('requires a label, street address, suburb and type (AC6)', function (): void {
    Livewire::test(Form::class)->call('save')
        ->assertHasErrors(['label', 'streetAddress', 'suburb', 'propertyType']);

    expect(Property::query()->count())->toBe(0);
});

it('uses the public id in URLs (AC7)', function (): void {
    $property = Property::factory()->for($this->customer)->create();

    expect(route('properties.edit', $property))->toEndWith('/app/properties/'.$property->public_id.'/edit');
    $this->get('/app/properties/'.$property->id.'/edit')->assertNotFound();
});

it('edits and soft-deletes my property (AC8)', function (): void {
    $property = Property::factory()->for($this->customer)->create(['label' => 'Home']);

    Livewire::test(Form::class, ['property' => $property])
        ->set('label', 'Old home')->call('selectSuburb', 'glenwood')->call('save')
        ->assertHasNoErrors();
    expect($property->fresh())->label->toBe('Old home')->suburb->slug->toBe('glenwood');

    Livewire::test(Index::class)->call('delete', $property->public_id);

    expect(Property::query()->count())->toBe(0)
        ->and(Property::withTrashed()->find($property->id)->trashed())->toBeTrue();
});

it("never shows or changes another customer's property (AC9)", function (): void {
    $theirs = Property::factory()->create(['label' => 'Their house']);

    $this->get(route('properties.edit', $theirs))->assertNotFound();
    Livewire::test(Index::class)->assertDontSee('Their house')->call('delete', $theirs->public_id);

    expect($theirs->fresh()->trashed())->toBeFalse();
});

it('saves a property in an inactive suburb with a note (AC10)', function (): void {
    addProperty(['suburb' => 'westville'])->assertHasNoErrors();

    Livewire::test(Index::class)->assertSee("Sortd isn't in Westville yet");
});

it('limits customers to 10 properties', function (): void {
    Property::factory()->count(10)->for($this->customer)->create();

    addProperty()->assertHasErrors(['label']);
    expect(Property::query()->count())->toBe(10);
});

it('keeps the street address encrypted and out of logs and the audit log (AC12)', function (): void {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message.' '.json_encode($event->context);
    });

    addProperty(['streetAddress' => '99 Secret Street']);

    $raw = DB::table('properties')->value('street_address');
    expect($raw)->not->toContain('Secret')
        ->and(collect($logged)->filter(fn (string $line): bool => str_contains($line, 'Secret'))->all())->toBe([]);

    $entry = Activity::query()->where('description', 'property created')->sole();
    expect(json_encode($entry->toArray()))->not->toContain('Secret');
});

it('keeps pros and admins out of the properties pages', function (): void {
    $this->actingAs(User::factory()->pro()->create())->get('/app/properties')->assertRedirect(route('pros.welcome'));
});

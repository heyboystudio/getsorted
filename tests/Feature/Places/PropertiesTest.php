<?php

declare(strict_types=1);

use App\Contracts\Data\GeocodedAddress;
use App\Contracts\Exceptions\GeocoderUnavailable;
use App\Contracts\Geocoder;
use App\Domain\Accounts\Enums\Role;
use App\Domain\Properties\Enums\PropertyType;
use App\Livewire\Account\Properties\Form;
use App\Livewire\Account\Properties\Index;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->customer = User::factory()->customer()->create();
    $this->actingAs($this->customer);
});

/** Adds a property the way customers do: search, pick a Google suggestion, choose the type and save. */
function addProperty(array $fields = []): Testable
{
    return Livewire::test(Form::class)
        ->set('label', $fields['label'] ?? 'Home')
        ->set('addressQuery', $fields['query'] ?? 'Innes')
        ->call('pickAddress', $fields['place'] ?? 'fake-morningside')
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

it('adds a property from a picked Google address with its point and area name (AC6, AC7, spec 020)', function (): void {
    addProperty()->assertHasNoErrors()->assertRedirect(route('properties.index'));

    $property = Property::query()->sole();

    expect($property->user_id)->toBe($this->customer->id)
        ->and($property->label)->toBe('Home')
        ->and($property->street_address)->toBe('12 Innes Road')
        ->and($property->area_label)->toBe('Morningside')
        ->and($property->location_source)->toBe('places')
        ->and($property->google_place_id)->toBe('fake-morningside')
        ->and($property->property_type)->toBe(PropertyType::House)
        ->and($property->public_id)->toHaveLength(26)
        ->and($property->location->getLatitude())->toEqualWithDelta(-29.827, 0.0001);
});

it('shows the privacy note and the address search (AC6)', function (): void {
    Livewire::test(Form::class)
        ->assertSee('We only share your street address with the pro you choose')
        ->assertSee('Find your address');
});

it('requires a name, a type and a picked address, and has no manual entry (AC6)', function (): void {
    Livewire::test(Form::class)->call('save')->assertHasErrors(['label', 'propertyType']);
    Livewire::test(Form::class)->set('label', 'Home')->set('propertyType', 'house')->call('save')->assertHasErrors(['addressQuery'])
        ->assertDontSee('Enter address manually');

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
        ->set('label', 'Old home')->set('addressQuery', 'Musgrave')->call('pickAddress', 'fake-berea')->call('save')
        ->assertHasNoErrors();
    expect($property->fresh())->label->toBe('Old home')->area_label->toBe('Berea')->and($property->fresh()->street_address)->toBe('10 Musgrave Road');

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

it('keeps the saved address when only the name changes', function (): void {
    $property = Property::factory()->for($this->customer)->create(['label' => 'Home', 'street_address' => '7 Private Lane', 'area_label' => 'Musgrave']);

    Livewire::test(Form::class, ['property' => $property])->set('label', 'Flat')->call('save')->assertHasNoErrors();

    expect($property->fresh())->label->toBe('Flat')->and($property->fresh()->street_address)->toBe('7 Private Lane')->and($property->fresh()->area_label)->toBe('Musgrave');
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

    addProperty();

    $raw = DB::table('properties')->value('street_address');
    expect($raw)->not->toContain('Innes')
        ->and(collect($logged)->filter(fn (string $line): bool => str_contains($line, 'Innes'))->all())->toBe([]);

    $entry = Activity::query()->where('description', 'property created')->sole();
    expect(json_encode($entry->toArray()))->not->toContain('Innes');
});

it('keeps pros and admins out of the properties pages', function (): void {
    $this->actingAs(User::factory()->pro()->create())->get('/app/properties')->assertRedirect(route('pros.welcome'));

    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminSuper->value);
    $this->actingAs($admin)->get('/app/properties')->assertForbidden();
});

it('cannot open the edit form for someone else\'s property directly (AC9)', function (): void {
    $theirs = Property::factory()->create();

    Livewire::test(Form::class, ['property' => $theirs])->assertNotFound();
});

it('cannot point the form at another property from the browser (AC9)', function (): void {
    $theirs = Property::factory()->create();

    Livewire::test(Form::class)->set('publicId', $theirs->public_id);
})->throws(CannotUpdateLockedPropertyException::class);

it('shows a retry message, not a manual form, when address search is unavailable', function (): void {
    config()->set('getsorted.places.unavailable_for_test', true);
    app()->instance(Geocoder::class, new class implements Geocoder
    {
        public function autocomplete(string $query, string $sessionToken): array
        {
            throw new GeocoderUnavailable('down');
        }

        public function resolve(string $placeId, string $sessionToken): ?GeocodedAddress
        {
            return null;
        }
    });

    Livewire::test(Form::class)->set('addressQuery', 'Innes')->assertSet('addressUnavailable', true)
        ->assertSee('Address search isn’t available right now')->assertDontSee('manually');
});

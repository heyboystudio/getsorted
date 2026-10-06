<?php

declare(strict_types=1);

use App\Contracts\Data\GeocodedAddress;
use App\Contracts\Geocoder;
use App\Domain\Properties\Enums\PropertyType;
use App\Integrations\Fakes\FakeGeocoder;
use App\Integrations\Google\GooglePlacesGeocoder;
use App\Livewire\Account\Properties\Form;
use App\Livewire\Booking\Thread;
use App\Models\GeocoderUsage;
use App\Models\Property;
use App\Models\User;
use App\Settings\PlacesSettings;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// --- Property form (AC1–AC7) ----------------------------------------------------------

it('suggests addresses after three characters and fills the form on pick (AC1, AC3, AC5)', function (): void {
    $this->actingAs(User::factory()->customer()->create());

    $form = Livewire::test(Form::class)
        ->set('addressQuery', 'In')->assertSet('addressSuggestions', [])
        ->set('addressQuery', 'Innes')
        ->assertSet('addressSuggestions', [['id' => 'fake-morningside', 'text' => '12 Innes Road, Morningside, Durban']])
        ->call('pickAddress', 'fake-morningside')
        ->assertSet('streetAddress', '12 Innes Road')
        ->assertSet('areaLabel', 'Morningside')
        ->assertSet('postalCode', '4001')
        ->assertSee('Address confirmed.');

    $form->set('label', 'Home')->set('propertyType', PropertyType::House->value)->call('save')->assertHasNoErrors();

    $property = Property::query()->sole();
    expect($property->location_source)->toBe('places')
        ->and($property->google_place_id)->toBe('fake-morningside')
        ->and(round($property->location->getLatitude(), 3))->toBe(-29.827);
});

it('refuses to pick a place that was not suggested', function (): void {
    $this->actingAs(User::factory()->customer()->create());

    Livewire::test(Form::class)->call('pickAddress', 'fake-berea')->assertNotFound();
});

// --- Fallbacks and limits (AC8, AC10, AC11) --------------------------------------------

it('asks the customer to try again when the provider fails, and records usage without address text (AC8, AC11, spec 020)', function (): void {
    Http::fake(['places.googleapis.com/*' => Http::response(['error' => ['message' => '12 Innes Road']], 500)]);
    app()->instance(Geocoder::class, new GooglePlacesGeocoder('test-key'));
    $this->actingAs(User::factory()->customer()->create());

    Livewire::test(Form::class)->set('addressQuery', '12 Innes Road')
        ->assertSet('addressUnavailable', true)
        ->assertSee('Address search isn’t available right now')->assertDontSee('manually');

    $usage = GeocoderUsage::query()->sole();
    expect($usage->purpose)->toBe('autocomplete')->and($usage->outcome)->toBe('error')
        ->and(json_encode($usage->toArray()))->not->toContain('Innes');
});

it('stops searching after the daily session cap (AC10)', function (): void {
    $settings = app(PlacesSettings::class);
    $settings->daily_session_cap = 1;
    $settings->save();
    $this->actingAs(User::factory()->customer()->create());

    Livewire::test(Form::class)->set('addressQuery', 'Innes')->assertSet('addressUnavailable', false);
    Livewire::test(Form::class)->set('addressQuery', 'Innes')->assertSet('addressUnavailable', true);
});

it('sends Google only the typed text, limited to South Africa, with a session token (AC1, AC2)', function (): void {
    Http::fake(['places.googleapis.com/v1/places:autocomplete' => Http::response(['suggestions' => [
        ['placePrediction' => ['placeId' => 'abc', 'text' => ['text' => '12 Innes Road, Morningside']]],
    ]])]);

    $result = (new GooglePlacesGeocoder('test-key'))->autocomplete('12 Innes', 'token-1');

    expect($result[0]->placeId)->toBe('abc');
    Http::assertSent(fn ($request): bool => $request->hasHeader('X-Goog-Api-Key', 'test-key')
        && $request['input'] === '12 Innes' && $request['sessionToken'] === 'token-1' && $request['includedRegionCodes'] === ['za']);
});

it('reads street, suburb, postal code and location from place details (AC3)', function (): void {
    Http::fake(['places.googleapis.com/v1/places/abc*' => Http::response([
        'formattedAddress' => '12 Innes Rd, Morningside, Durban, 4001, South Africa',
        'location' => ['latitude' => -29.827, 'longitude' => 31.017],
        'addressComponents' => [
            ['longText' => '12', 'types' => ['street_number']],
            ['longText' => 'Innes Road', 'types' => ['route']],
            ['longText' => 'Morningside', 'types' => ['sublocality_level_1', 'sublocality', 'political']],
            ['longText' => 'Durban', 'types' => ['locality', 'political']],
            ['longText' => '4001', 'types' => ['postal_code']],
        ],
    ])]);

    $address = (new GooglePlacesGeocoder('test-key'))->resolve('abc', 'token-1');

    expect($address)->streetLine->toBe('12 Innes Road')->suburb->toBe('Morningside')->postalCode->toBe('4001')
        ->and($address->areaNames)->toBe(['Morningside', 'Durban'])->and($address->areaLabel())->toBe('Morningside');
});

// --- Booking coverage step (AC5, AC6) ---------------------------------------------------

it('checks pros near the address straight after it is added in the booking thread (AC5, AC6; spec 017 AC11, spec 020)', function (): void {
    $this->seed(CatalogueSeeder::class);
    $trade = tradeOf('plumbing');
    proNear(['plumbing'], 2);
    $this->actingAs(User::factory()->customer()->create());

    describeJob(threadFor($trade), $trade)->call('addProperty')
        ->set('addressQuery', 'Innes')->call('pickAddress', 'fake-morningside')
        ->assertSet('newArea', 'Morningside')->assertSet('newStreet', fn (string $street): bool => $street !== '')
        ->set('newType', 'house')->call('saveProperty')
        ->assertSet('stage', 'when');

    app()->instance(Geocoder::class, new class implements Geocoder
    {
        public function autocomplete(string $query, string $sessionToken): array
        {
            return (new FakeGeocoder)->autocomplete($query, $sessionToken);
        }

        public function resolve(string $placeId, string $sessionToken): ?GeocodedAddress
        {
            return new GeocodedAddress('3 Beach Road, Ballito', 'Ballito', -29.5390, 31.2140, '3 Beach Road', '4420', ['Ballito']);
        }
    });

    session()->forget(Thread::SESSION_KEY);
    describeJob(threadFor($trade), $trade)->call('addProperty')
        ->set('addressQuery', 'Musgrave')->call('pickAddress', 'fake-berea')
        ->assertSet('newArea', 'Ballito')
        ->set('newType', 'flat')->call('saveProperty')
        ->assertSet('stage', 'waitlist');
});

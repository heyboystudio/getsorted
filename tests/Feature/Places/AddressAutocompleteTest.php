<?php

declare(strict_types=1);

use App\Contracts\Data\GeocodedAddress;
use App\Contracts\Geocoder;
use App\Domain\Properties\Enums\PropertyType;
use App\Domain\Properties\Queries\MatchSuburbQuery;
use App\Integrations\Fakes\FakeGeocoder;
use App\Integrations\Google\GooglePlacesGeocoder;
use App\Livewire\Account\Properties\Form;
use App\Livewire\Booking\Thread;
use App\Models\GeocoderUsage;
use App\Models\Pro;
use App\Models\Property;
use App\Models\Service;
use App\Models\Suburb;
use App\Models\User;
use App\Settings\PlacesSettings;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\SuburbSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SuburbSeeder::class);
});

// --- Property form (AC1–AC7) ----------------------------------------------------------

it('suggests addresses after three characters and fills the form on pick (AC1, AC3, AC5)', function (): void {
    $this->actingAs(User::factory()->customer()->create());

    $form = Livewire::test(Form::class)
        ->set('addressQuery', 'In')->assertSet('addressSuggestions', [])
        ->set('addressQuery', 'Innes')
        ->assertSet('addressSuggestions', [['id' => 'fake-morningside', 'text' => '12 Innes Road, Morningside, Durban']])
        ->call('pickAddress', 'fake-morningside')
        ->assertSet('streetAddress', '12 Innes Road')
        ->assertSet('suburb', 'morningside')
        ->assertSet('postalCode', '4001')
        ->assertSee('Good news, we cover Morningside.');

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

it('falls back to the suburb centre when the suburb is changed by hand after a pick', function (): void {
    $this->actingAs(User::factory()->customer()->create());

    Livewire::test(Form::class)
        ->set('addressQuery', 'Innes')->call('pickAddress', 'fake-morningside')
        ->call('selectSuburb', 'musgrave')
        ->set('label', 'Home')->set('propertyType', PropertyType::House->value)->call('save')->assertHasNoErrors();

    expect(Property::query()->sole())->location_source->toBe('suburb_centroid')->google_place_id->toBeNull();
});

it('matches suburbs by name, alias, prefix and then the nearest centre (AC4)', function (): void {
    $match = fn (array $areas, float $lat = 0.0, float $lng = 0.0): ?string => app(MatchSuburbQuery::class)
        ->handle(new GeocodedAddress('x', $areas[0] ?? null, $lat, $lng, areaNames: $areas))?->slug;

    Suburb::query()->where('slug', 'glenwood')->update(['aliases' => json_encode(['Bulwer'])]);

    expect($match(['Umhlanga Rocks', 'Durban']))->toBe('umhlanga')
        ->and($match(['bulwer']))->toBe('glenwood')
        ->and($match(['Nowhere'], -29.8230, 31.0095))->toBe('morningside')
        ->and($match(['Nowhere'], -26.2, 28.0))->toBeNull();
});

it('adds an unknown Durban suburb from the address and covers it (decision 044)', function (): void {
    $address = fn (string $area): GeocodedAddress => new GeocodedAddress("1 Main Road, {$area}", $area, -29.6300, 31.0500, '1 Main Road', '4340', [$area, 'Durban'], 'eThekwini Metropolitan Municipality');

    $suburb = app(MatchSuburbQuery::class)->handle($address('Ottawa'));

    expect($suburb)->name->toBe('Ottawa')->is_active->toBeTrue()->municipality->toBe('eThekwini')
        ->and(app(MatchSuburbQuery::class)->handle($address('Ottawa'))->id)->toBe($suburb->id)
        ->and(app(MatchSuburbQuery::class)->handle(new GeocodedAddress('x', 'Durban', -29.8580, 31.0220, areaNames: ['Durban'], municipality: 'eThekwini Metropolitan Municipality'))->slug)->toBe('durban_central');
});

it('does not add suburbs for addresses outside eThekwini', function (): void {
    $count = Suburb::query()->count();

    app(MatchSuburbQuery::class)->handle(new GeocodedAddress('x', 'Ballito', -29.5390, 31.2140, areaNames: ['Ballito'], municipality: 'KwaDukuza Local Municipality'));

    expect(Suburb::query()->count())->toBe($count);
});

it('lets the customer choose a suburb when none matches (AC7)', function (): void {
    $this->actingAs(User::factory()->customer()->create());
    app()->instance(Geocoder::class, new class implements Geocoder
    {
        public function autocomplete(string $query, string $sessionToken): array
        {
            return (new FakeGeocoder)->autocomplete($query, $sessionToken);
        }

        public function resolve(string $placeId, string $sessionToken): ?GeocodedAddress
        {
            return new GeocodedAddress('1 Far Road, Johannesburg', 'Johannesburg', -26.2, 28.0, '1 Far Road', '2000', ['Johannesburg']);
        }
    });

    Livewire::test(Form::class)->set('addressQuery', 'Innes')->call('pickAddress', 'fake-morningside')
        ->assertSet('streetAddress', '1 Far Road')->assertSet('suburb', null);
});

// --- Fallbacks and limits (AC8, AC10, AC11) --------------------------------------------

it('switches to manual entry when the provider fails, and records usage without address text (AC8, AC11)', function (): void {
    Http::fake(['places.googleapis.com/*' => Http::response(['error' => ['message' => '12 Innes Road']], 500)]);
    app()->instance(Geocoder::class, new GooglePlacesGeocoder('test-key'));
    $this->actingAs(User::factory()->customer()->create());

    Livewire::test(Form::class)->set('addressQuery', '12 Innes Road')
        ->assertSet('addressManual', true)
        ->assertDontSee('Find your address');

    $usage = GeocoderUsage::query()->sole();
    expect($usage->purpose)->toBe('autocomplete')->and($usage->outcome)->toBe('error')
        ->and(json_encode($usage->toArray()))->not->toContain('Innes');
});

it('stops searching after the daily session cap (AC10)', function (): void {
    $settings = app(PlacesSettings::class);
    $settings->daily_session_cap = 1;
    $settings->save();
    $this->actingAs(User::factory()->customer()->create());

    Livewire::test(Form::class)->set('addressQuery', 'Innes')->assertSet('addressManual', false);
    Livewire::test(Form::class)->set('addressQuery', 'Innes')->assertSet('addressManual', true);
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
        ->and($address->areaNames)->toBe(['Morningside', 'Durban']);
});

// --- Booking coverage step (AC5, AC6) ---------------------------------------------------

it('checks coverage straight after an address is added in the booking thread (AC5, AC6; spec 017 AC11)', function (): void {
    $this->seed(CatalogueSeeder::class);
    $leak = Service::query()->where('key', 'leak_repair')->sole();
    $pro = Pro::factory()->approved()->create();
    $pro->services()->attach($leak);
    $pro->serviceAreas()->attach(Suburb::query()->where('slug', 'morningside')->sole());
    $this->actingAs(User::factory()->customer()->create());

    describeJob(threadFor($leak), $leak)->call('addProperty')
        ->set('addressQuery', 'Innes')->call('pickAddress', 'fake-morningside')
        ->assertSet('newSuburb', 'morningside')->assertSet('newStreet', fn (string $street): bool => $street !== '')
        ->set('newType', 'house')->call('saveProperty')
        ->assertSet('stage', 'when');

    session()->forget(Thread::SESSION_KEY);
    describeJob(threadFor($leak), $leak)->call('addProperty')
        ->set('addressQuery', 'Musgrave')->call('pickAddress', 'fake-berea')
        ->assertSet('newSuburb', 'berea')
        ->set('newType', 'flat')->call('saveProperty')
        ->assertSet('stage', 'waitlist');
});

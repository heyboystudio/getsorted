<?php

declare(strict_types=1);

use App\Contracts\Data\ChatRequest;
use App\Contracts\ScopingAssistant;
use App\Domain\ServiceJobs\Actions\SaveBookingDraft;
use App\Domain\ServiceJobs\Data\BookingData;
use App\Domain\ServiceJobs\Enums\TimeWindow;
use App\Livewire\Booking\Thread;
use App\Models\Pro;
use App\Models\Property;
use App\Models\ServiceJob;
use App\Models\Trade;
use App\Models\User;
use App\Settings\AiSettings;
use Carbon\CarbonImmutable;
use Clickbar\Magellan\Data\Geometries\Point;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Helpers shared by the booking, matching and quote tests (spec 020: trades, facts and distance).
 * Tests that use them seed the catalogue first: `$this->seed(CatalogueSeeder::class)`.
 */

/** The middle of Durban: the default point of the Property and Pro factories. */
function durban(): Point
{
    return Point::makeGeodetic(-29.8587, 31.0218);
}

/** A point roughly $km kilometres due north of Durban's centre (1 degree of latitude is about 111 km). */
function kmNorthOfDurban(float $km): Point
{
    return Point::makeGeodetic(-29.8587 + $km / 111.0, 31.0218);
}

function tradeOf(string $key = 'plumbing'): Trade
{
    return Trade::query()->where('key', $key)->firstOrFail();
}

/**
 * An approved pro of the given trades, based $kmFromDurban km north of the city centre.
 *
 * @param  list<string>  $tradeKeys
 */
function proNear(array $tradeKeys = ['plumbing'], float $kmFromDurban = 0, int $radiusKm = 15): Pro
{
    $pro = Pro::factory()->approved()->create([
        'base_location' => kmNorthOfDurban($kmFromDurban),
        'service_radius_km' => $radiusKm,
    ]);
    $pro->trades()->attach(Trade::query()->whereIn('key', $tradeKeys)->pluck('id')->all());

    return $pro;
}

/** @return array{0: User, 1: Property} */
function bookingCustomer(): array
{
    $customer = User::factory()->customer()->create();
    $property = Property::factory()->for($customer)->create(['label' => 'Home', 'street_address' => '7 Private Lane']);

    return [$customer, $property];
}

/** Opens the thread, optionally with a trade chosen, as a trade link does. */
function threadFor(?Trade $trade = null): Testable
{
    return Livewire::test(Thread::class, $trade instanceof Trade ? ['trade' => $trade] : []);
}

/**
 * The customer describes the job and Siya (the scripted fake) records the trade and facts through the real
 * toolbox, then offers the next step, exactly as the model would. The thread then shows the secure controls.
 *
 * @param  list<string>  $facts
 */
function describeJob(Testable $thread, Trade $trade, array $facts = ['tap drips when fully closed']): Testable
{
    $settings = app(AiSettings::class);
    $settings->enabled = true;
    $settings->save();

    $said = ucfirst(implode('. ', $facts)).'. I need someone to book.';

    app(ScopingAssistant::class)->willChat(function (ChatRequest $request) use ($trade, $facts): string {
        $request->toolbox->setTrade($trade->key);

        foreach ($facts as $fact) {
            $request->toolbox->addFact($fact, $fact);
        }

        $request->toolbox->offerNextStep('sign_in');

        return 'Got it. Let’s get this booked.';
    });

    return $thread->set('message', $said)->call('send');
}

/** From Where & when to the summary: property, a day and window, then photos skipped. */
function bookUpToSummary(Testable $thread, Property $property, ?string $date = null, string $window = 'morning'): Testable
{
    return $thread->call('selectProperty', $property->public_id)
        ->set('preferredDate', $date ?? now()->addDays(3)->toDateString())->call('chooseWhen', $window)
        ->call('finishPhotos');
}

/** A draft job ready to post, with the facts a customer would have given. */
function draftJob(User $customer, Trade $trade, array $overrides = []): ServiceJob
{
    return app(SaveBookingDraft::class)->handle($customer, $trade, null, new BookingData(
        facts: array_key_exists('facts', $overrides) ? $overrides['facts'] : [['id' => 'f1', 'text' => 'tap drips when fully closed', 'turn' => 1]],
        notes: array_key_exists('notes', $overrides) ? $overrides['notes'] : 'Under the kitchen sink.',
        propertyPublicId: array_key_exists('property', $overrides) ? $overrides['property'] : test()->property->public_id,
        preferredDate: array_key_exists('date', $overrides) ? $overrides['date'] : CarbonImmutable::today('Africa/Johannesburg')->addDays(2),
        timeWindow: array_key_exists('window', $overrides) ? $overrides['window'] : TimeWindow::Morning,
        urgent: $overrides['urgent'] ?? false,
    ));
}

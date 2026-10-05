<?php

declare(strict_types=1);

use App\Contracts\ScopingAssistant;
use App\Domain\Assistant\Enums\AiPurpose;
use App\Integrations\Fakes\FakeScopingAssistant;
use App\Livewire\Assistant\Chat;
use App\Livewire\Booking\Wizard;
use App\Models\AiUsage;
use App\Models\Pro;
use App\Models\Service;
use App\Models\Suburb;
use App\Models\Trade;
use App\Settings\AiSettings;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\SuburbSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([CatalogueSeeder::class, SuburbSeeder::class]);
    $this->plumbing = Trade::query()->where('key', 'plumbing')->sole();
    $this->leak = Service::query()->where('key', 'leak_repair')->sole();
    $pro = Pro::factory()->approved()->create();
    $pro->services()->attach($this->leak);
    $pro->serviceAreas()->attach(Suburb::query()->where('slug', 'morningside')->sole());

    $settings = app(AiSettings::class);
    $settings->enabled = true;
    $settings->save();
});

function siya(): FakeScopingAssistant
{
    /** @var FakeScopingAssistant */
    return app(ScopingAssistant::class);
}

it('greets as an AI assistant called Siya and is linked from the home page', function (): void {
    $this->get('/')->assertSee(route('assistant'), false)->assertSee('Get help with a job');
    $this->get(route('assistant'))->assertOk()->assertSee("Hi, I'm Siya, Sortd's AI assistant.");
});

it('suggests a valid service and waits for the customer to confirm (AC1, AC2)', function (): void {
    siya()->willChat('Sounds like a leaking tap. Is that right?', 'plumbing', 'leak_repair');

    Livewire::test(Chat::class)
        ->set('message', 'My kitchen tap is dripping, call me on 082 123 4567')->call('send')
        ->assertSet('suggestedServiceId', $this->leak->id)->assertSet('serviceId', null)
        ->assertSee('Leak repair');

    $transcript = siya()->chatRequests()[0]->transcript;
    expect(end($transcript)['text'])->not->toContain('082 123 4567');
    expect(AiUsage::query()->sole()->purpose)->toBe(AiPurpose::Chat);
});

it('ignores services that are not in the active catalogue (AC2)', function (): void {
    siya()->willChat('Sounds like roofing.', 'roofing', 'roof_repair');

    Livewire::test(Chat::class)->set('message', 'Roof leaks')->call('send')->assertSet('suggestedServiceId', null);
});

it('discards replies with prices or contact details and shows a retry message (AC7, AC11)', function (): void {
    siya()->willChat('That will cost about R450.')->willChat('Call 082 123 4567.');

    $chat = Livewire::test(Chat::class)->set('message', 'Leaking tap')->call('send')->assertSee('Sorry, something went wrong');
    $chat->set('message', 'Hello?')->call('send')->assertSet('failures', 2)->assertSee('Siya is having trouble');
});

it('shows stored safety advice and collects answers by chip or by typing, then hands over to booking (AC3, AC4, AC6)', function (): void {
    siya()->willChat('Sounds like a leak.', 'plumbing', 'leak_repair')
        ->willChat('Thanks. Is it dripping or flowing?', answers: ['leak_location' => ['Tap'], 'severity' => ['Not a real option']]);

    $chat = Livewire::test(Chat::class)
        ->set('message', 'Water leaking from my tap')->call('send')
        ->call('confirmService')
        ->assertSee('If water is flooding, close the main stopcock first.')
        ->set('message', 'It is the tap')->call('send')
        ->assertSet('answers', ['leak_location' => 'Tap'])
        ->call('answer', 'severity', 'Dripping')
        ->assertSet('answers', ['leak_location' => 'Tap', 'severity' => 'Dripping'])
        ->assertSee('Continue booking');

    $chat->call('continueBooking')->assertRedirect(route('booking.start', ['trade' => $this->plumbing, 'service' => 'leak_repair']));

    // In booking, the address picks the suburb, coverage passes, and the answered questions are skipped.
    Livewire::test(Wizard::class, ['trade' => $this->plumbing, 'service' => $this->leak])
        ->assertSet('answers', ['leak_location' => 'Tap', 'severity' => 'Dripping'])
        ->assertSet('notes', "Water leaking from my tap\nIt is the tap")
        ->set('addressQuery', 'Innes')->call('pickAddress', 'fake-morningside')
        ->assertSet('step', 'notes');
});

it('refuses a chip answer for a question that is not being asked', function (): void {
    siya()->willChat('Sounds like a leak.', 'plumbing', 'leak_repair');

    Livewire::test(Chat::class)->set('message', 'Leak')->call('send')->call('confirmService')
        ->call('answer', 'severity', 'Dripping')->assertNotFound();
});

it('shows the stop-first card for gas, sparks, smoke or flooding (AC4)', function (): void {
    Livewire::test(Chat::class)->set('message', 'I can see sparks from the plug')->call('send')
        ->assertSee('Safety first')->assertSee('switch off at the mains');
});

it('stops after the message limit and can start over (AC8, AC10)', function (): void {
    config()->set('sortd.ai.chat_messages_per_conversation', 1);

    Livewire::test(Chat::class)
        ->set('message', 'Leak')->call('send')->assertHasNoErrors()
        ->set('message', 'Again')->call('send')->assertHasErrors('message')
        ->set('message', 'start over')->call('send')
        ->assertSet('customerMessages', 0)->assertCount('messages', 1);
});

it('keeps the chat after a refresh', function (): void {
    siya()->willChat('Sounds like a leak.', 'plumbing', 'leak_repair');
    Livewire::test(Chat::class)->set('message', 'Leaking tap')->call('send');

    Livewire::test(Chat::class)->assertSee('Leaking tap')->assertSet('suggestedServiceId', $this->leak->id);
});

it('does not call the assistant when it is switched off (AC12)', function (): void {
    $settings = app(AiSettings::class);
    $settings->enabled = false;
    $settings->save();

    Livewire::test(Chat::class)->assertSee('Siya is switched off right now.')->assertDontSee('Type your message');
    expect(siya()->chatRequests())->toBe([]);
});

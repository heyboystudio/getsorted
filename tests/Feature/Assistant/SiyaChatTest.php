<?php

declare(strict_types=1);

use App\Contracts\ScopingAssistant;
use App\Domain\Assistant\Enums\AiPurpose;
use App\Integrations\Fakes\FakeScopingAssistant;
use App\Livewire\Booking\Thread;
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

/*
 * Siya inside the booking thread (specs 016 and 017): free text, the single
 * service confirmation, skipping questions already answered, limits and fallbacks.
 */

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
    $this->get('/')->assertSee(route('book'), false)->assertSee('Get help with a job');
    $this->get(route('book'))->assertOk()->assertSee('I’m Siya, Sortd’s AI assistant');
});

it('suggests a valid service with one confirmation card, not two (spec 017 AC6)', function (): void {
    siya()->willChat('Sounds like a leaking tap. Is that right?', 'plumbing', 'leak_repair');

    Livewire::test(Thread::class)
        ->set('message', 'My kitchen tap is dripping, call me on 082 123 4567')->call('send')
        ->assertSet('suggestedServiceId', $this->leak->id)->assertSet('serviceId', null)->assertSet('stage', 'suggested')
        ->assertSee('Leak repair')->assertDontSee('Sounds like a leaking tap. Is that right?');

    $transcript = siya()->chatRequests()[0]->transcript;
    expect(end($transcript)['text'])->not->toContain('082 123 4567');
    expect(AiUsage::query()->sole()->purpose)->toBe(AiPurpose::Chat);
});

it('ignores services that are not in the active catalogue', function (): void {
    siya()->willChat('Sounds like roofing.', 'roofing', 'roof_repair');

    Livewire::test(Thread::class)->set('message', 'Roof leaks')->call('send')->assertSet('suggestedServiceId', null)->assertSee('Sounds like roofing.');
});

it('skips questions the first message already answered once the service is confirmed (spec 017 AC7)', function (): void {
    siya()->willChat('Sounds like a leak.', 'plumbing', 'leak_repair')
        ->willChat('Got it, a dripping tap. Anything else?', answers: ['leak_location' => ['Tap'], 'severity' => ['Dripping']]);

    $thread = Livewire::test(Thread::class)
        ->set('message', 'My kitchen tap is dripping')->call('send')
        ->call('confirmService')
        ->assertSet('answers', ['leak_location' => 'Tap', 'severity' => 'Dripping'])
        ->assertSet('stage', 'details')
        ->assertDontSee('Where is the leak coming from?');

    // The second call carried the confirmed service and the customer's own words.
    expect(siya()->chatRequests()[1]->confirmedServiceKey)->toBe('leak_repair');
    $thread->assertSet('notes', 'My kitchen tap is dripping');
});

it('asks only what is still missing, and accepts typed or tapped answers', function (): void {
    siya()->willChat('Sounds like a leak.', 'plumbing', 'leak_repair')
        ->willChat('Where is it leaking from?', answers: ['severity' => ['Steady flow'], 'leak_location' => ['Not a real option']])
        ->willChat('Thanks, a pipe.', answers: ['leak_location' => ['Pipe']]);

    Livewire::test(Thread::class)
        ->set('message', 'Water is pouring out')->call('send')->call('confirmService')
        ->assertSet('answers', ['severity' => 'Steady flow'])->assertSet('stage', 'questions')
        ->assertSee('Where is it leaking from?')
        ->set('message', 'It is a pipe under the sink')->call('send')
        ->assertSet('answers', ['severity' => 'Steady flow', 'leak_location' => 'Pipe'])->assertSet('stage', 'details');
});

it('lets the customer turn a suggestion down and describe it again', function (): void {
    siya()->willChat('Sounds like a leak.', 'plumbing', 'leak_repair');

    Livewire::test(Thread::class)->set('message', 'Water everywhere')->call('send')
        ->call('rejectService')->assertSet('stage', 'describe')->assertSet('suggestedServiceId', null)->assertSee('Plumbing');
});

it('discards replies with prices or contact details and shows a retry message', function (): void {
    siya()->willChat('That will cost about R450.')->willChat('Call 082 123 4567.');

    Livewire::test(Thread::class)
        ->set('message', 'Leaking tap')->call('send')->assertSee('Sorry, something went wrong')
        ->set('message', 'Hello?')->call('send')->assertSet('failures', 2)->assertDontSee('R450');
});

it('shows the stop-first card for gas, sparks, smoke or flooding', function (): void {
    Livewire::test(Thread::class)->set('message', 'I can see sparks from the plug')->call('send')
        ->assertSee('Safety first')->assertSee('switch off at the mains');
});

it('stops after the message limit and can start over', function (): void {
    config()->set('sortd.ai.chat_messages_per_conversation', 1);

    Livewire::test(Thread::class)
        ->set('message', 'Leak')->call('send')->assertHasNoErrors()
        ->set('message', 'Again')->call('send')->assertHasErrors('message')
        ->set('message', 'start over')->call('send')
        ->assertSet('customerMessages', 0)->assertCount('messages', 1);
});

it('keeps the chat after a refresh', function (): void {
    siya()->willChat('Sounds like a leak.', 'plumbing', 'leak_repair');
    Livewire::test(Thread::class)->set('message', 'Leaking tap')->call('send');

    Livewire::test(Thread::class)->assertSee('Leaking tap')->assertSet('suggestedServiceId', $this->leak->id);
});

it('starts with the text from the account home box (spec 017 AC1)', function (): void {
    siya()->willChat('Sounds like a leak.', 'plumbing', 'leak_repair');
    session()->put(Thread::START_KEY, 'No hot water and the geyser is dripping');

    Livewire::test(Thread::class)->assertSee('No hot water and the geyser is dripping')->assertSet('stage', 'suggested');
    expect(session()->has(Thread::START_KEY))->toBeFalse();
});

it('works by taps alone when the assistant is off, keeping typed text for the pro (spec 017 AC17)', function (): void {
    $settings = app(AiSettings::class);
    $settings->enabled = false;
    $settings->save();

    Livewire::test(Thread::class)
        ->assertSee('Tap an option to continue')
        ->set('message', 'The tap in the kitchen drips')->call('send')
        ->assertSee('I can’t read messages right now')->assertSet('notes', 'The tap in the kitchen drips')
        ->call('pickTrade', 'plumbing')->call('pickService', 'leak_repair')
        ->assertSet('stage', 'questions')->assertSee('Where is the leak coming from?');

    expect(siya()->chatRequests())->toBe([]);
});

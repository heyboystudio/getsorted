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
 * Siya inside the booking thread (specs 016 and 017): free text, the quiet
 * service identification, skipping questions already answered, limits and fallbacks.
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
    $this->get(route('book'))->assertOk()->assertSee('I’m Siya, Get Sorted’s AI assistant');
});

it('identifies a valid service quietly with no confirmation card (spec 019 AC7)', function (): void {
    siya()->willChat('I can help with that leaking tap.', 'plumbing', 'leak_repair');

    Livewire::test(Thread::class)
        ->set('message', 'My kitchen tap is dripping, call me on 082 123 4567')->call('send')
        ->assertSet('suggestedServiceId', null)->assertSet('serviceId', $this->leak->id)->assertSet('stage', 'questions')
        ->assertSee('I can help with that leaking tap.')->assertDontSee('Is that right?');

    $transcript = siya()->chatRequests()[0]->transcript;
    expect(end($transcript)['text'])->not->toContain('082 123 4567');
    expect(AiUsage::query()->sole()->purpose)->toBe(AiPurpose::Chat);
});

it('ignores services that are not in the active catalogue', function (): void {
    siya()->willChat('Sounds like roofing.', 'roofing', 'roof_repair');

    Livewire::test(Thread::class)->set('message', 'Roof leaks')->call('send')->assertSet('suggestedServiceId', null)->assertDontSee('Sounds like roofing.')->assertSee('Try again');
});

it('skips questions already answered when the service is identified (spec 019 AC8)', function (): void {
    siya()->willChat('Got it, a dripping tap.', 'plumbing', 'leak_repair', answers: ['leak_location' => ['Tap'], 'severity' => ['Dripping']]);

    $thread = Livewire::test(Thread::class)
        ->set('message', 'My kitchen tap is dripping')->call('send')
        ->assertSet('answers', ['leak_location' => 'Tap', 'severity' => 'Dripping'])
        ->assertSet('stage', 'signin')
        ->assertDontSee('Where is the leak coming from?');

    expect(siya()->chatRequests())->toHaveCount(1);
    $thread->assertSet('notes', 'My kitchen tap is dripping');
});

it('asks only what is still missing, and accepts typed or tapped answers', function (): void {
    siya()->willChat('Where is it leaking from?', 'plumbing', 'leak_repair', answers: ['severity' => ['Steady flow'], 'leak_location' => ['Not a real option']])
        ->willChat('Thanks, a pipe.', answers: ['leak_location' => ['Pipe']]);

    Livewire::test(Thread::class)
        ->set('message', 'Water is pouring out')->call('send')
        ->assertSet('answers', ['severity' => 'Steady flow'])->assertSet('stage', 'questions')
        ->assertSee('Where is it leaking from?')
        ->set('message', 'It is a pipe under the sink')->call('send')
        ->assertSet('answers', ['severity' => 'Steady flow', 'leak_location' => 'Pipe'])->assertSet('stage', 'signin');
});

it('lets the customer correct a quietly identified service', function (): void {
    $drain = Service::query()->where('key', 'blocked_drain')->sole();
    siya()->willChat('Tell me where it leaks.', 'plumbing', 'leak_repair')
        ->willChat('Thanks, let’s scope the blocked drain.', 'plumbing', 'blocked_drain');

    Livewire::test(Thread::class)->set('message', 'Water everywhere')->call('send')
        ->set('message', 'Actually the drain is blocked')->call('send')
        ->assertSet('serviceId', $drain->id)->assertSet('answers', [])->assertSet('stage', 'questions');
});

it('discards replies with prices or contact details and shows a retry message', function (): void {
    siya()->willChat('That will cost about R450.')->willChat('Call 082 123 4567.');

    Livewire::test(Thread::class)
        ->set('message', 'Leaking tap')->call('send')->assertSee('Sorry, something went wrong')
        ->set('message', 'Hello?')->call('send')->assertSet('failures', 2)->assertDontSee('R450');
});

it('shows the stop-first card for gas, sparks, smoke or flooding', function (): void {
    Livewire::test(Thread::class)->set('message', 'I can see sparks from the plug')->call('send')
        ->assertSee('Safety first')->assertSee('031 361 0000')->assertSet('stage', 'emergency');
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

    Livewire::test(Thread::class)->assertSee('Leaking tap')->assertSet('serviceId', $this->leak->id);
});

it('starts with the text from the account home box (spec 017 AC1)', function (): void {
    siya()->willChat('Sounds like a leak.', 'plumbing', 'leak_repair');
    session()->put(Thread::START_KEY, 'No hot water and the geyser is dripping');

    Livewire::test(Thread::class)->assertSee('No hot water and the geyser is dripping')->assertSet('stage', 'questions');
    expect(session()->has(Thread::START_KEY))->toBeFalse();
});

it('works by taps alone when the assistant is off, retaining uninterpreted text in the conversation (spec 017 AC17)', function (): void {
    $settings = app(AiSettings::class);
    $settings->enabled = false;
    $settings->save();

    Livewire::test(Thread::class)
        ->assertSee('Tap an option to continue')
        ->set('message', 'The tap in the kitchen drips')->call('send')
        ->assertSee('I can’t read messages right now')->assertSet('notes', '')->assertSee('The tap in the kitchen drips')
        ->call('pickTrade', 'plumbing')->call('pickService', 'leak_repair')
        ->assertSet('stage', 'questions')->assertSee('Where is the leak coming from?');

    expect(siya()->chatRequests())->toBe([]);
});

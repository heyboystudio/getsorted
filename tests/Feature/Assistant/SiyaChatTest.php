<?php

declare(strict_types=1);

use App\Contracts\Data\ChatRequest;
use App\Contracts\ScopingAssistant;
use App\Domain\Assistant\Enums\AiPurpose;
use App\Integrations\Fakes\FakeScopingAssistant;
use App\Livewire\Booking\Thread;
use App\Models\AiUsage;
use App\Settings\AiSettings;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\Console\Command\Command;

/*
 * Siya inside the booking thread (specs 016, 017, 020): free text, the quiet trade and fact recording through
 * validated tools, never re-asking what is known, corrections, limits and fallbacks.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
    $this->plumbing = tradeOf('plumbing');
    proNear(['plumbing'], 2);

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
    $this->get('/')->assertSee('Start a job');
    [$customer] = bookingCustomer();
    $this->actingAs($customer)->get(route('book'))->assertOk()->assertSee('I’m Siya, GetSorted’s AI assistant');
});

it('records the trade and several facts from the first message, with no confirmation card (spec 019 AC7, spec 020)', function (): void {
    siya()->willChat(function (ChatRequest $request): string {
        $request->toolbox->setTrade('plumbing');
        $request->toolbox->addFact('kitchen tap drips', 'kitchen tap is dripping');
        $request->toolbox->addFact('started two days ago', 'two days');

        return 'I can help with that dripping tap.';
    });

    Livewire::test(Thread::class)
        ->set('message', 'My kitchen tap is dripping, been going for two days. Call me on 082 123 4567')->call('send')
        ->assertSet('tradeId', $this->plumbing->id)->assertSet('facts', fn (array $facts): bool => array_column($facts, 'text') === ['kitchen tap drips', 'started two days ago'])
        ->assertSee('I can help with that dripping tap.')->assertDontSee('Is that right?');

    $transcript = siya()->chatRequests()[0]->transcript;
    expect(end($transcript)['text'])->not->toContain('082 123 4567')->toContain('[phone]');
    expect(AiUsage::query()->sole()->purpose)->toBe(AiPurpose::Chat);
});

it('ignores trades that are not offered and still answers the customer (no more "Try again")', function (): void {
    siya()->willChat(function (ChatRequest $request): string {
        $rejected = $request->toolbox->setTrade('roofing');
        expect($rejected['ok'])->toBeFalse();

        return 'We don’t do roofing yet, sorry.';
    });

    Livewire::test(Thread::class)->set('message', 'Roof leaks')->call('send')
        ->assertSet('tradeId', null)->assertSee('We don’t do roofing yet, sorry.')->assertDontSee('Try again')->assertSet('failures', 0);
});

it('never stores a fact the customer did not say', function (): void {
    siya()->willChat(function (ChatRequest $request): string {
        $request->toolbox->setTrade('plumbing');
        expect($request->toolbox->addFact('pipe is copper', 'the pipe is made of copper')['ok'])->toBeFalse();

        return 'Is it a tap or a pipe?';
    });

    Livewire::test(Thread::class)->set('message', 'My tap is dripping')->call('send')->assertSet('facts', [])->assertSee('Is it a tap or a pipe?');
});

it('tells the model what is already known on the next turn, so nothing is asked twice (audit P1)', function (): void {
    siya()->willChat(function (ChatRequest $request): string {
        $request->toolbox->setTrade('plumbing');
        $request->toolbox->addFact('tap drips', 'tap is dripping');

        return 'Got it.';
    })->willChat(fn (): string => 'Thanks.');

    Livewire::test(Thread::class)->set('message', 'My tap is dripping')->call('send')->set('message', 'It started yesterday')->call('send');

    $digest = siya()->chatRequests()[1]->toolbox->digest();
    expect($digest['trade']['key'])->toBe('plumbing')->and(array_column($digest['facts'], 'text'))->toBe(['tap drips'])
        ->and($digest['still_needed'])->toBe([])->and($digest['ready_for_next_step'])->toBeTrue();
});

it('accepts a correction: removes a fact and changes the trade without losing the rest', function (): void {
    siya()->willChat(function (ChatRequest $request): string {
        $request->toolbox->setTrade('plumbing');
        $request->toolbox->addFact('kitchen tap leaks', 'kitchen tap leaks');
        $request->toolbox->addFact('light keeps tripping the power', 'light keeps tripping');

        return 'Two things there. Which first?';
    })->willChat(function (ChatRequest $request): string {
        $facts = $request->toolbox->digest()['facts'];
        $request->toolbox->removeFact($facts[0]['id']);
        $request->toolbox->setTrade('electrical');
        $request->toolbox->parkJob('kitchen tap leaks');

        return 'No problem, the light first.';
    });

    Livewire::test(Thread::class)->set('message', 'My kitchen tap leaks and the light keeps tripping the power')->call('send')
        ->set('message', 'Do the light first, that one is more annoying')->call('send')
        ->assertSet('tradeId', tradeOf('electrical')->id)
        ->assertSet('facts', fn (array $facts): bool => array_column($facts, 'text') === ['light keeps tripping the power'])
        ->assertSet('parked', ['kitchen tap leaks']);
});

it('stops after the message limit and can start over', function (): void {
    config()->set('getsorted.ai.chat_messages_per_conversation', 1);

    Livewire::test(Thread::class)
        ->set('message', 'Leak')->call('send')->assertHasNoErrors()
        ->set('message', 'Again')->call('send')->assertHasErrors('message')
        ->set('message', 'start over')->call('send')
        ->assertSet('customerMessages', 0)->assertCount('messages', 1);
});

it('keeps the chat after a refresh', function (): void {
    siya()->willChat(function (ChatRequest $request): string {
        $request->toolbox->setTrade('plumbing');
        $request->toolbox->addFact('tap leaks', 'leaking tap');

        return 'Sounds like a leak.';
    });
    Livewire::test(Thread::class)->set('message', 'Leaking tap')->call('send');

    Livewire::test(Thread::class)->assertSee('Leaking tap')->assertSet('tradeId', $this->plumbing->id)->assertSet('facts.0.text', 'tap leaks');
});

it('starts with the text from the account home box (spec 017 AC1)', function (): void {
    siya()->willChat(function (ChatRequest $request): string {
        $request->toolbox->setTrade('plumbing');
        $request->toolbox->addFact('no hot water', 'no hot water');

        return 'Sounds like a geyser problem.';
    });
    session()->put(Thread::START_KEY, 'No hot water and the geyser is dripping');

    Livewire::test(Thread::class)->assertSee('No hot water and the geyser is dripping')->assertSet('facts.0.text', 'no hot water')->assertSee('Continue to book');
    expect(session()->has(Thread::START_KEY))->toBeFalse();
});

it('works by taps alone when the assistant is off, keeping the customer’s own words as notes (spec 017 AC17)', function (): void {
    $settings = app(AiSettings::class);
    $settings->enabled = false;
    $settings->save();

    Livewire::test(Thread::class)
        ->set('message', 'The tap in the kitchen drips')->call('send')
        ->assertSee('I can’t read messages right now')->assertSet('notes', 'The tap in the kitchen drips')
        ->call('pickTrade', 'plumbing')->assertSet('tradeId', $this->plumbing->id)->assertSee('Continue to book')
        ->call('startBooking')->assertSet('stage', 'signin');

    expect(siya()->chatRequests())->toBe([]);
});

it('does not ask for the problem again after a trade is picked without the assistant (audit)', function (): void {
    $settings = app(AiSettings::class);
    $settings->enabled = false;
    $settings->save();

    Livewire::test(Thread::class)
        ->set('message', 'My geyser is leaking through the ceiling')->call('send')
        ->call('pickTrade', 'plumbing')
        ->assertDontSee('Tell me in your own words')
        ->assertSee('your description is saved for the pros')
        ->assertDontSee('Safety advice');
});

it('refuses to run the live evaluation without --live and without a provider, so it never spends budget by accident', function (): void {
    $this->artisan('siya:eval')->assertExitCode(Command::INVALID);

    $settings = app(AiSettings::class);
    $settings->enabled = false;
    $settings->save();
    $this->artisan('siya:eval', ['--live' => true])->assertFailed();
});

it('evaluates conversations by state with the scripted assistant, using the same engine as the thread', function (): void {
    siya()->willChat(function (ChatRequest $request): string {
        $request->toolbox->setTrade('plumbing');
        $request->toolbox->addFact('kitchen tap drips non stop', 'tap is dripping non stop');

        return 'Sounds like a dripping tap.';
    });

    $this->artisan('siya:eval', ['--live' => true, '--only' => 'dripping tap, stated once'])->expectsOutputToContain('PASS')->assertSuccessful();
});

it('treats every message as an ordinary job: no emergency stop and no safety advice (founder 2026-10-08, decision 060)', function (): void {
    $settings = app(AiSettings::class);
    $settings->enabled = false;
    $settings->save();

    Livewire::test(Thread::class)
        ->set('message', 'I smell gas and there are sparks from the plug')->call('send')
        ->assertSet('stage', 'chat')->assertSee('I can’t read messages right now')
        ->assertDontSee('031 361 0000')->assertDontSee('Safety first')->assertDontSee('Discuss a later repair')
        ->call('pickTrade', 'electrical')->assertSet('tradeId', tradeOf('electrical')->id)->assertDontSee('Safety advice');
});

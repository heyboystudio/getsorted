<?php

declare(strict_types=1);

use App\Contracts\Data\ChatRequest;
use App\Contracts\ScopingAssistant;
use App\Domain\Accounts\Enums\Role;
use App\Domain\ServiceJobs\Enums\ActorType;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\Urgency;
use App\Domain\ServiceJobs\Support\BookingReturn;
use App\Filament\Admin\Resources\ServiceJobs\Pages\ListServiceJobs;
use App\Filament\Admin\Resources\ServiceJobs\Pages\ViewServiceJob;
use App\Jobs\SendJobPostedMessage;
use App\Livewire\Account\Home;
use App\Livewire\Account\Properties\Form;
use App\Livewire\Auth\Login;
use App\Livewire\Booking\Thread;
use App\Models\Property;
use App\Models\ServiceJob;
use App\Models\ServiceJobEvent;
use App\Models\Trade;
use App\Models\User;
use App\Settings\AiSettings;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogueSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/*
 * Booking in one Siya thread (specs 017, 020): the customer describes the problem, Siya records the trade and
 * facts through validated tools, then the secure controls, drafts, posting, the job list and the admin view.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
    $this->plumbing = tradeOf('plumbing');
    proNear(['plumbing'], 2);
});

/** A saved address far from every pro, for the waitlist. */
function remoteProperty(User $customer): Property
{
    return Property::factory()->for($customer)->create(['label' => 'Cottage', 'area_label' => 'Ballito', 'location' => kmNorthOfDurban(60)]);
}

it('opens the thread from the home page and trade pages (AC1, AC3)', function (): void {
    $this->get('/')->assertOk()->assertSee(route('register'), false);
    $this->get(route('trades.show', $this->plumbing))->assertOk()->assertSee('Start a plumbing job')->assertSee(route('register'), false);
    $this->get(route('assistant'))->assertRedirect('/book');

    [$customer] = bookingCustomer();
    $this->actingAs($customer);
    $this->get(route('book.trade', $this->plumbing))->assertOk()->assertSee('What’s the plumbing problem?');

    $this->plumbing->update(['is_active' => false]);
    $this->get(route('book.trade', $this->plumbing))->assertNotFound();
});

it('starts with Siya’s greeting and optional trade shortcuts (AC2, AC5)', function (): void {
    Livewire::test(Thread::class)
        ->assertSee('I’m Siya, GetSorted’s AI assistant')->assertSet('stage', 'chat')->assertDontSee('Continue to book')
        ->call('showTrades')->assertSee('Plumbing')
        ->call('pickTrade', 'plumbing')->assertSee('What’s the plumbing problem?')->assertSet('tradeId', $this->plumbing->id);
});

it('lets Siya record the trade and facts from one natural message, with no questionnaire', function (): void {
    describeJob(threadFor(), $this->plumbing, ['kitchen tap drips when fully closed', 'started two days ago'])
        ->assertSet('tradeId', $this->plumbing->id)
        ->assertSet('stage', 'signin')
        ->assertSet('facts.0.text', 'kitchen tap drips when fully closed')
        ->assertSee('Got it. Let’s get this booked.')->assertSee('What I’ve got so far')->assertSee('started two days ago');
});

it('never leaves the customer at a dead end: when there is enough, Continue to book is offered (audit)', function (): void {
    $settings = app(AiSettings::class);
    $settings->enabled = true;
    $settings->save();
    app(ScopingAssistant::class)->willChat(function (ChatRequest $request): string {
        $request->toolbox->setTrade('plumbing');
        $request->toolbox->addFact('tap drips', 'my tap drips');

        return 'Sounds like a dripping tap. Anything else a pro should know?';
    });

    threadFor()->set('message', 'My tap drips')->call('send')
        ->assertSet('stage', 'chat')->assertSee('Continue to book')
        ->call('startBooking')->assertSet('stage', 'signin')->assertSee('Please sign in to book');
});

it('refuses to continue to booking before a trade and a problem are known', function (): void {
    threadFor($this->plumbing)->call('startBooking')->assertNotFound();
});

it('lets the customer remove a fact Siya got wrong', function (): void {
    $thread = describeJob(threadFor(), $this->plumbing, ['tap drips', 'pipe is burst']);
    $id = $thread->get('facts')[1]['id'];

    $thread->call('removeFact', $id)->assertSet('facts', fn (array $facts): bool => count($facts) === 1 && $facts[0]['text'] === 'tap drips');
});

it('keeps Siya for signed-in customers: guests are sent to sign in', function (): void {
    $this->get(route('book'))->assertRedirect(route('login'));
    $this->get(route('book.trade', $this->plumbing))->assertRedirect(route('login'));
    $this->get(route('assistant'))->assertRedirect('/book');
});

it('marks the job urgent when Siya flags urgency, without any safety advice (AC8, decision 060)', function (): void {
    [$customer] = bookingCustomer();
    $this->actingAs($customer);
    $settings = app(AiSettings::class);
    $settings->enabled = true;
    $settings->save();
    app(ScopingAssistant::class)->willChat(function (ChatRequest $request): string {
        $request->toolbox->setTrade('plumbing');
        $request->toolbox->addFact('pipe is flooding the kitchen', 'pipe is flooding');
        $request->toolbox->setUrgency(true);
        $request->toolbox->offerNextStep('location');

        return 'That sounds urgent. Let’s get a plumber out.';
    });

    threadFor()->set('message', 'My pipe is flooding the kitchen')->call('send')
        ->assertDontSee('stopcock')->assertSet('stage', 'where');

    expect(ServiceJob::query()->sole()->urgency)->toBe(Urgency::Urgent);
});

it('replaces a reply that breaks the rules with a plain one built from the state, keeping the facts', function (): void {
    $settings = app(AiSettings::class);
    $settings->enabled = true;
    $settings->save();
    app(ScopingAssistant::class)
        ->willChat(function (ChatRequest $request): string {
            $request->toolbox->setTrade('plumbing');
            $request->toolbox->addFact('tap drips', 'my tap drips');

            return 'That will cost about R500.';
        })
        ->willChat(fn (): string => 'Call me on 082 123 4567.');

    threadFor()->set('message', 'My tap drips')->call('send')
        ->assertSee('Would you like to go ahead and book a pro?')->assertDontSee('R500')->assertDontSee('082 123')
        ->assertSet('facts.0.text', 'tap drips')->assertSet('failures', 0);
});

it('keeps validated facts when the provider fails, and a retry does not duplicate anything', function (): void {
    $settings = app(AiSettings::class);
    $settings->enabled = true;
    $settings->save();
    $fake = app(ScopingAssistant::class);
    $fake->willFail();

    $thread = threadFor($this->plumbing)->set('message', 'My tap drips')->call('send')
        ->assertSet('retryPending', true)->assertSee('Tap Try again');

    $fake->willChat(function (ChatRequest $request): string {
        $request->toolbox->addFact('tap drips', 'my tap drips');

        return 'Thanks, noted.';
    });
    $fake->clearFailure();

    $thread->call('retry')->assertSet('retryPending', false)->assertSee('Thanks, noted.');

    $customerMessages = collect($thread->get('messages'))->where('role', 'customer')->where('text', 'My tap drips');
    expect($customerMessages)->toHaveCount(1)->and($thread->get('facts'))->toHaveCount(1);
});

it('keeps the customer’s own words as notes and lets them carry on when Siya is switched off', function (): void {
    $settings = app(AiSettings::class);
    $settings->enabled = false;
    $settings->save();

    threadFor()->call('showTrades')->call('pickTrade', 'plumbing')
        ->set('message', 'The tap in the kitchen drips all day')->call('send')
        ->assertSet('notes', 'The tap in the kitchen drips all day')->assertSee('Continue to book')
        ->call('startBooking')->assertSet('stage', 'signin');
});

it('asks guests to sign in before the address and keeps everything through login (AC10)', function (): void {
    $thread = describeJob(threadFor($this->plumbing), $this->plumbing)
        ->assertSet('stage', 'signin')->assertSee('Sign in to book');
    expect(ServiceJob::query()->count())->toBe(0);

    $thread->call('signIn')->assertRedirect(route('login'));
    expect(session('url.intended'))->toBe(route('book.trade', $this->plumbing, false));

    User::factory()->customer()->create(['email' => 'thandi@example.com']);
    Livewire::test(Login::class)->set('email', 'thandi@example.com')->set('password', 'password')->call('login')
        ->assertRedirect(route('book.trade', $this->plumbing));

    $this->actingAs(User::query()->where('email', 'thandi@example.com')->sole());
    threadFor($this->plumbing)->assertSet('stage', 'where')->assertSee('Where do you need the work done?')
        ->assertSet('facts.0.text', 'tap drips when fully closed');
    expect(ServiceJob::query()->sole()->factTexts())->toBe(['tap drips when fully closed']);
});

it('sends guests to sign up from the same card', function (): void {
    describeJob(threadFor($this->plumbing), $this->plumbing)->call('signUp')->assertRedirect(route('register'));
});

it('books end to end in one thread: address, day and window, photos, summary, confirm (AC10–AC16)', function (): void {
    Queue::fake();
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);
    $date = now()->addDays(3)->toDateString();

    $thread = describeJob(threadFor($this->plumbing), $this->plumbing)
        ->assertSet('stage', 'where')->assertSee('7 Private Lane')
        ->call('selectProperty', $property->public_id)
        ->assertSee('Location confirmed')->assertSet('stage', 'when')
        ->call('chooseWhen', 'morning')->assertHasErrors(['when'])
        ->set('preferredDate', $date)->call('chooseWhen', 'morning')
        ->assertSee('Date confirmed')->assertSet('stage', 'photos')->assertSee('Skip for now')
        ->call('finishPhotos')->assertSet('stage', 'summary')
        ->assertSee('Here’s a summary of your booking')->assertSee('7 Private Lane')->assertSee('Morning (07:00–12:00)')
        ->assertSee('tap drips when fully closed')->assertSee('Confirm booking');

    $job = ServiceJob::query()->sole();
    expect($job->status)->toBe(ServiceJobStatus::Draft);

    $thread->call('confirmBooking')->assertSet('stage', 'posted')->assertSee('Your job is booked')->assertSee(route('jobs.show', $job));

    $job->refresh();
    expect($job->status)->toBe(ServiceJobStatus::Open)
        ->and($job->factTexts())->toBe(['tap drips when fully closed'])
        ->and($job->area_label)->toBe('Musgrave')->and($job->trade_id)->toBe($this->plumbing->id);
    Queue::assertPushedOn('notifications', SendJobPostedMessage::class);
});

it('never books without the Confirm booking tap', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    $thread = describeJob(threadFor($this->plumbing), $this->plumbing)->call('selectProperty', $property->public_id);
    $thread->call('confirmBooking')->assertNotFound();

    expect(ServiceJob::query()->sole()->status)->toBe(ServiceJobStatus::Draft);
});

it('does not let Siya’s offer or a typed "yes, book it" post the job', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    describeJob(threadFor($this->plumbing), $this->plumbing)->call('selectProperty', $property->public_id)
        ->set('message', 'yes, book it now')->call('send');

    expect(ServiceJob::query()->sole()->status)->toBe(ServiceJobStatus::Draft);
});

it('offers the waitlist when no pro is near the address, with the account’s details (AC11)', function (): void {
    [$customer] = bookingCustomer();
    $remote = remoteProperty($customer);
    $this->actingAs($customer);

    describeJob(threadFor($this->plumbing), $this->plumbing)
        ->call('selectProperty', $remote->public_id)
        ->assertSet('stage', 'waitlist')->assertSee('we don’t have plumbing pros near Ballito yet')
        ->call('joinWaitlist')->assertSet('stage', 'closed')->assertSee('No job has been posted');

    $this->assertDatabaseHas('waitlist_entries', ['phone_e164' => $customer->phone_e164, 'trade_id' => $this->plumbing->id, 'area_label' => 'Ballito']);
});

it('lets the customer choose a different trade or say no thanks from the waitlist (AC11)', function (): void {
    [$customer] = bookingCustomer();
    $remote = remoteProperty($customer);
    $this->actingAs($customer);

    describeJob(threadFor($this->plumbing), $this->plumbing)->call('selectProperty', $remote->public_id)
        ->call('noThanks')->assertSet('stage', 'closed')
        ->call('differentTrade')->assertSet('stage', 'chat')->assertSet('tradeId', null);
    $this->assertDatabaseCount('waitlist_entries', 0);
});

it("cannot pick another customer's property", function (): void {
    [$customer] = bookingCustomer();
    $theirs = Property::factory()->create();
    $this->actingAs($customer);

    describeJob(threadFor($this->plumbing), $this->plumbing)->call('selectProperty', $theirs->public_id)->assertNotFound();
});

it('adds a new address inside the thread through Places and checks pros straight away (AC10, AC11)', function (): void {
    $customer = User::factory()->customer()->create();
    $this->actingAs($customer);

    describeJob(threadFor($this->plumbing), $this->plumbing)->assertSee('Add your address')
        ->call('addProperty')->assertSet('stage', 'add_property')
        ->call('saveProperty')->assertHasErrors(['newType'])
        ->set('newType', 'house')->call('saveProperty')->assertHasErrors(['addressQuery'])
        ->set('addressQuery', 'Innes')->call('pickAddress', 'fake-morningside')
        ->assertSet('newStreet', '12 Innes Road')->assertSet('newArea', 'Morningside')
        ->call('saveProperty')->assertHasNoErrors()->assertSet('stage', 'when')->assertSee('Location confirmed');

    $property = $customer->properties()->sole();
    expect($property->label)->toBe('Home')->and($property->street_address)->toBe('12 Innes Road')
        ->and($property->area_label)->toBe('Morningside')->and($property->location_source)->toBe('places');
});

it('offers "urgent — today" for every trade (spec 020, D-a)', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);
    proNear(['painting'], 2);
    $painting = tradeOf('painting');

    describeJob(threadFor($painting), $painting)->call('selectProperty', $property->public_id)->assertSet('stage', 'when')->assertSee('Urgent — today');
});

it('uses Durban time for "today" (urgent bookings after midnight SAST)', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);
    // 23:30 UTC on the 10th is 01:30 SAST on the 11th.
    $this->travelTo(CarbonImmutable::parse('2026-11-10 23:30:00', 'UTC'));

    describeJob(threadFor($this->plumbing), $this->plumbing)->call('selectProperty', $property->public_id)
        ->call('chooseWhen', 'today')->call('finishPhotos')->assertSet('stage', 'summary')->assertSee('Urgent')
        ->call('confirmBooking')->assertHasNoErrors();

    expect(ServiceJob::query()->sole()->preferred_date->toDateString())->toBe('2026-11-11');
});

it('treats a tampered or out-of-range date as not chosen instead of an error page', function (string $date): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    describeJob(threadFor($this->plumbing), $this->plumbing)->call('selectProperty', $property->public_id)
        ->set('preferredDate', $date)->call('chooseWhen', 'morning')->assertHasErrors(['when'])->assertSet('stage', 'when');
})->with(['2026-99-99', 'yesterday', '2099-01-01']);

it('changes a section from the summary and comes straight back to it (AC15)', function (): void {
    [$customer, $property] = bookingCustomer();
    $other = Property::factory()->for($customer)->create(['label' => 'Flat', 'street_address' => '9 Other Road']);
    $this->actingAs($customer);

    $thread = bookUpToSummary(describeJob(threadFor($this->plumbing), $this->plumbing), $property);

    $thread->call('change', 'where')->assertSet('stage', 'where')
        ->call('selectProperty', $other->public_id)->assertSet('stage', 'summary')->assertSee('9 Other Road');
    $thread->call('change', 'details')->assertSet('stage', 'notes')->set('notesDraft', 'Gate code 1234')->call('saveNotes')
        ->assertSet('stage', 'summary')->assertSee('Gate code 1234');
    $thread->call('change', 'when')->set('preferredDate', now()->addDays(5)->toDateString())->call('chooseWhen', 'afternoon')
        ->assertSet('stage', 'summary')->assertSee('Afternoon (12:00–17:00)');

    expect(ServiceJob::query()->sole()->property_id)->toBe($other->id);
});

it('changes the trade from the summary and keeps what the customer said', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    bookUpToSummary(describeJob(threadFor($this->plumbing), $this->plumbing), $property)
        ->call('change', 'trade')->assertSet('stage', 'chat')->assertSet('tradeId', null)
        ->assertSet('facts.0.text', 'tap drips when fully closed')->assertSee('Everything you told me is kept');
});

it('removes a fact from the summary editor', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);
    $thread = bookUpToSummary(describeJob(threadFor($this->plumbing), $this->plumbing, ['tap drips', 'wrong detail']), $property);
    $id = $thread->get('facts')[1]['id'];

    $thread->call('change', 'details')->call('removeFact', $id)->call('saveNotes')->assertSet('stage', 'summary');

    expect(ServiceJob::query()->sole()->factTexts())->toBe(['tap drips']);
});

it('enforces the notes limit', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    bookUpToSummary(describeJob(threadFor($this->plumbing), $this->plumbing), $property)
        ->call('change', 'details')->set('notesDraft', str_repeat('a', 1001))->call('saveNotes')->assertHasErrors(['notesDraft']);

    expect(ServiceJob::query()->sole()->customer_notes)->toBeNull();
});

it('shows the posted message again on a second Confirm tap instead of an error', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    $thread = bookUpToSummary(describeJob(threadFor($this->plumbing), $this->plumbing), $property);
    $thread->call('confirmBooking')->call('confirmBooking')->assertHasNoErrors()->assertSet('stage', 'posted');

    expect(ServiceJob::query()->count())->toBe(1);
});

it('does not create a draft just by opening a trade, and reuses a draft for the same trade', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    threadFor($this->plumbing);
    expect(ServiceJob::query()->count())->toBe(0);

    describeJob(threadFor($this->plumbing), $this->plumbing)->call('selectProperty', $property->public_id);
    $draft = ServiceJob::query()->sole();

    session()->forget(Thread::SESSION_KEY);
    describeJob(threadFor($this->plumbing), $this->plumbing, ['another detail'])->assertSet('jobPublicId', $draft->public_id);
    expect(ServiceJob::query()->count())->toBe(1);
});

it('keeps an unfinished booking as a draft to resume in the thread (spec 005 AC12)', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    describeJob(threadFor($this->plumbing), $this->plumbing)->call('selectProperty', $property->public_id);
    $job = ServiceJob::query()->sole();

    Livewire::test(Home::class)->assertSee('Finish your request')->assertSee(route('booking.continue', $job));
    Livewire::test(Thread::class, ['job' => $job])->assertSet('stage', 'when')->assertSet('propertyPublicId', $property->public_id)
        ->assertSet('facts.0.text', 'tap drips when fully closed')->assertSee('When do you need help?');
});

it('keeps the thread after a refresh and starts over on Restart (AC18)', function (): void {
    describeJob(threadFor($this->plumbing), $this->plumbing);

    threadFor($this->plumbing)->assertSet('facts.0.text', 'tap drips when fully closed')->assertSee('What I’ve got so far')
        ->call('restart')->assertSet('tradeId', null)->assertSet('facts', [])->assertSet('stage', 'chat');
});

it('lets customers remove an unfinished request', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);
    describeJob(threadFor($this->plumbing), $this->plumbing)->call('selectProperty', $property->public_id);
    $draft = ServiceJob::query()->sole();

    Livewire::test(Home::class)->assertSee('Remove')->call('removeDraft', $draft->public_id)->assertDontSee('Finish your request');

    expect($draft->fresh()->status)->toBe(ServiceJobStatus::Cancelled)
        ->and(ServiceJobEvent::query()->sole()->actor_type)->toBe(ActorType::Customer);

    $other = ServiceJob::factory()->create(['trade_id' => tradeOf('plumbing')->id]);
    Livewire::test(Home::class)->call('removeDraft', $other->public_id);
    expect($other->fresh()->status)->toBe(ServiceJobStatus::Draft);
});

it('lists my jobs and keeps other customers out (spec 005 AC13, AC14)', function (): void {
    [$customer, $property] = bookingCustomer();
    $mine = ServiceJob::factory()->open()->forProperty($property)->create(['trade_id' => tradeOf('plumbing')->id]);
    $theirs = ServiceJob::factory()->open()->create(['trade_id' => tradeOf('plumbing')->id]);
    $this->actingAs($customer);

    Livewire::test(Home::class)->assertSee('Plumbing')->assertSee('Finding your pros');
    $this->get(route('jobs.show', $mine))->assertOk();
    $this->get(route('jobs.show', $theirs))->assertNotFound();
    $this->get('/app/jobs/'.$mine->id)->assertNotFound();
    $this->get(route('booking.continue', $theirs))->assertNotFound();
    $this->get(route('booking.continue', $mine))->assertNotFound();
});

it('shows jobs to every admin role without street address or phone (spec 005 AC15)', function (Role $role): void {
    [, $property] = bookingCustomer();
    $job = ServiceJob::factory()->open()->forProperty($property)->create([
        'trade_id' => tradeOf('plumbing')->id, 'customer_notes' => 'Behind the fridge', 'area_label' => 'Musgrave',
        'facts' => [['id' => 'f1', 'text' => 'tap drips when closed', 'turn' => 1]],
    ]);
    $event = new ServiceJobEvent(['from_status' => 'draft', 'to_status' => 'open', 'event_type' => 'job_posted', 'actor_type' => 'customer', 'actor_id' => $job->customer_id]);
    $event->serviceJob()->associate($job)->save();
    $admin = User::factory()->create();
    $admin->assignRole($role->value);
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');

    Livewire::test(ListServiceJobs::class)->assertCanSeeTableRecords([$job])->assertDontSee('7 Private Lane')->assertDontSee($property->user->phone_e164);
    Livewire::test(ViewServiceJob::class, ['record' => $job->public_id])
        ->assertSee('Musgrave')->assertSee('tap drips when closed')->assertSee('Behind the fridge')->assertSee('Job posted')
        ->assertDontSee('7 Private Lane')->assertDontSee($property->user->phone_e164);

    expect($admin->can('update', $job))->toBeFalse()->and($admin->can('delete', $job))->toBeFalse();
})->with([Role::AdminSuper, Role::AdminSupport, Role::AdminVetting, Role::AdminFinance]);

it('keeps customers and pros out of the admin jobs list (spec 005 AC15)', function (): void {
    $this->actingAs(User::factory()->customer()->create())->get('/admin/service-jobs')->assertForbidden();
});

it('only accepts GetSorted booking paths as a return address after adding a property', function (?string $return, ?string $expected): void {
    expect(BookingReturn::sanitise($return))->toBe($expected);
})->with([
    'booking start' => ['/book/plumbing', '/book/plumbing'],
    'booking' => ['/book', '/book'],
    'draft continue' => ['/app/jobs/01HZX3V5Q8M2N4P6R8T0W2Y4A6/continue', '/app/jobs/01HZX3V5Q8M2N4P6R8T0W2Y4A6/continue'],
    'other site' => ['https://evil.example/book/a/b', null],
    'protocol relative' => ['//evil.example', null],
    'trailing newline' => ["/book/plumbing\n", null],
    'other page' => ['/app/properties', null],
    'missing' => [null, null],
]);

it('returns to the booking after adding a property from the properties page', function (): void {
    [$customer] = bookingCustomer();
    $this->actingAs($customer);

    Livewire::withQueryParams(['return' => '/book/plumbing'])->test(Form::class)
        ->set('label', 'Flat')->set('addressQuery', 'Innes')->call('pickAddress', 'fake-morningside')->set('propertyType', 'flat')
        ->call('save')->assertRedirect('/book/plumbing');

    Livewire::withQueryParams(['return' => 'https://evil.example'])->test(Form::class)
        ->set('label', 'Office')->set('addressQuery', 'Musgrave')->call('pickAddress', 'fake-berea')->set('propertyType', 'business')
        ->call('save')->assertRedirect(route('properties.index'));
});

it('saves only the day that was checked, even if the calendar value is changed afterwards', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);
    $date = now()->addDays(3)->toDateString();

    bookUpToSummary(describeJob(threadFor($this->plumbing), $this->plumbing), $property, $date)
        ->set('preferredDate', '2020-01-01')->call('confirmBooking')->assertHasNoErrors();

    expect(ServiceJob::query()->sole()->preferred_date->toDateString())->toBe($date);
});

it('offers to book the second job the customer mentioned, once the first is posted', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);
    $settings = app(AiSettings::class);
    $settings->enabled = true;
    $settings->save();
    app(ScopingAssistant::class)->willChat(function (ChatRequest $request): string {
        $request->toolbox->setTrade('plumbing');
        $request->toolbox->addFact('tap drips', 'tap drips');
        $request->toolbox->parkJob('bathroom light keeps tripping');
        $request->toolbox->offerNextStep('location');

        return 'Tap first, then the light.';
    });

    $thread = threadFor()->set('message', 'My tap drips and the bathroom light keeps tripping')->call('send');
    bookUpToSummary($thread, $property)->call('confirmBooking')->assertSet('stage', 'posted')
        ->assertSee('bathroom light keeps tripping')->assertSee('Book “bathroom light keeps tripping” next')
        ->call('startNextJob')->assertSet('stage', 'chat')->assertSet('tradeId', null)->assertSet('facts', [])->assertSet('parked', [])
        ->assertSee('Let’s sort out: bathroom light keeps tripping');

    expect(ServiceJob::query()->count())->toBe(1);
});

it('does not offer a next job when none was mentioned', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    bookUpToSummary(describeJob(threadFor($this->plumbing), $this->plumbing), $property)->call('confirmBooking')
        ->assertDontSeeHtml('wire:click="startNextJob"')->call('startNextJob')->assertNotFound();
});

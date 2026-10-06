<?php

declare(strict_types=1);

use App\Contracts\MessagingChannel;
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
use App\Models\Pro;
use App\Models\Property;
use App\Models\Service;
use App\Models\ServiceJob;
use App\Models\ServiceJobEvent;
use App\Models\Suburb;
use App\Models\Trade;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\SuburbSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/*
 * Booking in one Siya thread (spec 017), keeping spec 005's rules: questions,
 * drafts, posting, the job list and the admin view.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([CatalogueSeeder::class, SuburbSeeder::class]);
    $this->plumbing = Trade::query()->where('key', 'plumbing')->sole();
    $this->leak = Service::query()->where('key', 'leak_repair')->sole();
    $pro = Pro::factory()->approved()->create();
    $pro->services()->attach($this->leak);
    $pro->serviceAreas()->attach(Suburb::query()->where('slug', 'musgrave')->sole());
});

it('opens the thread from the home page, trade pages and service links (AC1, AC3)', function (): void {
    $this->get('/')->assertOk()->assertSee(route('book'), false);
    $this->get(route('trades.show', $this->plumbing))->assertOk()->assertSee('Leak repair')->assertSee(route('booking.start', [$this->plumbing, $this->leak]));
    $this->get(route('assistant'))->assertRedirect('/book');
    $this->get(route('booking.start', [$this->plumbing, $this->leak]))->assertOk()->assertSee('Where is the leak coming from?');

    $this->leak->update(['is_active' => false]);
    $this->get(route('trades.show', $this->plumbing))->assertDontSee('Leak repair');
    $this->get(route('booking.start', [$this->plumbing, $this->leak]))->assertNotFound();
});

it('starts with Siya’s greeting and the trades, then a trade’s services as chips (AC2, AC5)', function (): void {
    Livewire::test(Thread::class)
        ->assertSee('I’m Siya, Get Sorted’s AI assistant')->assertSee('Plumbing')->assertSet('stage', 'trade')
        ->call('pickTrade', 'plumbing')->assertSet('stage', 'service')
        ->assertSee('What’s the plumbing problem?')->assertSee('Leak repair')->assertSee('Other')
        ->call('pickService', 'leak_repair')
        ->assertSet('serviceId', $this->leak->id)->assertSee('You selected')->assertSee('Where is the leak coming from?');

    $this->get(route('book.trade', $this->plumbing))->assertOk()->assertSee('What’s the plumbing problem?');
});

it('asks one question at a time with tap answers and refuses invalid ones (AC7)', function (): void {
    threadFor($this->leak)
        ->assertSee('Where is the leak coming from?')->assertDontSee('How bad is it?')
        ->call('answer', 'leak_location', 'Not a real option')->assertHasErrors(['answer'])->assertSet('answers', [])
        ->call('answer', 'leak_location', 'Tap')->assertSee('How bad is it?')
        ->call('answer', 'leak_location', 'Pipe')->assertNotFound();
});

it('shows stored safety advice and marks the job urgent for an urgent answer (AC8)', function (): void {
    [$customer] = bookingCustomer();
    $this->actingAs($customer);

    threadFor($this->leak)->assertSee('If water is flooding, close the main stopcock first.')
        ->call('answer', 'leak_location', 'Pipe')->call('answer', 'severity', 'Flooding')
        ->assertSet('stage', 'where');

    expect(ServiceJob::query()->sole()->urgency)->toBe(Urgency::Urgent);
});

it('moves directly from scoping to booking details without a Continue gate (spec 019 AC13)', function (): void {
    answerQuestions(threadFor($this->leak), $this->leak)
        ->assertSet('stage', 'signin')->assertSet('detailsDone', true)
        ->assertDontSee('Anything else your pro should know?')->assertSee('Sign in to book');
});

it('asks guests to sign in before Where & when and keeps everything through login (AC10)', function (): void {
    $thread = describeJob(threadFor($this->leak), $this->leak)
        ->assertSet('stage', 'signin')->assertSee('Sign in to book');
    expect(ServiceJob::query()->count())->toBe(0);

    $thread->call('signIn')->assertRedirect(route('login'));
    expect(session('url.intended'))->toBe(route('booking.start', [$this->plumbing, $this->leak], false));

    User::factory()->customer()->create(['email' => 'thandi@example.com']);
    Livewire::test(Login::class)->set('email', 'thandi@example.com')->set('password', 'password')->call('login')
        ->assertRedirect(route('booking.start', [$this->plumbing, $this->leak]));

    $this->actingAs(User::query()->where('email', 'thandi@example.com')->sole());
    threadFor($this->leak)->assertSet('stage', 'where')->assertSee('Where do you need the work done?')
        ->assertSet('answers', ['leak_location' => 'Tap', 'severity' => 'Dripping']);
    expect(ServiceJob::query()->sole()->scoping_answers)->toHaveKeys(['leak_location', 'severity']);
});

it('sends guests to sign up from the same card', function (): void {
    describeJob(threadFor($this->leak), $this->leak)->call('signUp')->assertRedirect(route('register'));
});

it('books end to end in one thread: property, day and window, photos, summary, confirm (AC10–AC16)', function (): void {
    Queue::fake();
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);
    $date = now()->addDays(3)->toDateString();

    $thread = describeJob(threadFor($this->leak), $this->leak)
        ->assertSet('stage', 'where')->assertSee('7 Private Lane')
        ->call('selectProperty', $property->public_id)
        ->assertSee('Location confirmed')->assertSee('Good news, we have vetted pros for this in Musgrave.')->assertSet('stage', 'when')
        ->call('chooseWhen', 'morning')->assertHasErrors(['when'])
        ->set('preferredDate', $date)->call('chooseWhen', 'morning')
        ->assertSee('Date confirmed')->assertSet('stage', 'photos')->assertSee('Skip for now')
        ->call('finishPhotos')->assertSet('stage', 'summary')
        ->assertSee('Here’s a summary of your booking')->assertSee('7 Private Lane')->assertSee('Morning (07:00–12:00)')->assertSee('Confirm booking');

    $job = ServiceJob::query()->sole();
    expect($job->status)->toBe(ServiceJobStatus::Draft);

    $thread->call('confirmBooking')->assertSet('stage', 'posted')->assertSee('Your job is booked')->assertSee(route('jobs.show', $job));

    expect($job->fresh()->status)->toBe(ServiceJobStatus::Open)
        ->and(array_column($job->fresh()->orderedAnswers(), 'prompt'))->toBe(['Where is the leak coming from?', 'How bad is it?']);
    Queue::assertPushedOn('notifications', SendJobPostedMessage::class);
    app(MessagingChannel::class)->assertNothingSent();
});

it('never books without the Confirm booking tap', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    $thread = describeJob(threadFor($this->leak), $this->leak)->call('selectProperty', $property->public_id);
    $thread->call('confirmBooking')->assertNotFound();

    expect(ServiceJob::query()->sole()->status)->toBe(ServiceJobStatus::Draft);
});

it('offers the waitlist when no pros cover the property, with the account’s details (AC11)', function (): void {
    [$customer] = bookingCustomer();
    $westville = Property::factory()->for($customer)->create(['suburb_id' => Suburb::query()->where('slug', 'westville')->value('id')]);
    $this->actingAs($customer);

    describeJob(threadFor($this->leak), $this->leak)
        ->call('selectProperty', $westville->public_id)
        ->assertSet('stage', 'waitlist')->assertSee('we don’t have pros for Leak repair in Westville yet')
        ->call('joinWaitlist')->assertSet('stage', 'closed')->assertSee('No job has been posted');

    $this->assertDatabaseHas('waitlist_entries', ['phone_e164' => $customer->phone_e164, 'service_id' => $this->leak->id, 'suburb_key' => 'westville']);
});

it('lets the customer choose a different service or say no thanks from the waitlist (AC11)', function (): void {
    [$customer] = bookingCustomer();
    $westville = Property::factory()->for($customer)->create(['suburb_id' => Suburb::query()->where('slug', 'westville')->value('id')]);
    $this->actingAs($customer);

    $thread = describeJob(threadFor($this->leak), $this->leak)->call('selectProperty', $westville->public_id);
    $thread->call('noThanks')->assertSet('stage', 'closed')
        ->call('differentService')->assertSet('stage', 'trade')->assertSet('serviceId', null)->assertSet('answers', []);
    $this->assertDatabaseCount('waitlist_entries', 0);
});

it("cannot pick another customer's property", function (): void {
    [$customer] = bookingCustomer();
    $theirs = Property::factory()->create();
    $this->actingAs($customer);

    describeJob(threadFor($this->leak), $this->leak)->call('selectProperty', $theirs->public_id)->assertNotFound();
});

it('adds a new property inside the thread and checks coverage straight away (AC10, AC11)', function (): void {
    $customer = User::factory()->customer()->create();
    $this->actingAs($customer);

    describeJob(threadFor($this->leak), $this->leak)->assertSee('Add your address')
        ->call('addProperty')->assertSet('stage', 'add_property')
        ->call('enterAddressManually')
        ->call('saveProperty')->assertHasErrors(['newStreet', 'newSuburb', 'newType'])
        ->set('newStreet', '12 Innes Road')->set('newSuburbQuery', 'Musg')->assertSee('Musgrave')
        ->call('selectNewSuburb', 'musgrave')->set('newType', 'house')
        ->call('saveProperty')->assertHasNoErrors()->assertSet('stage', 'when')->assertSee('Location confirmed');

    $property = $customer->properties()->sole();
    expect($property->label)->toBe('Home')->and($property->street_address)->toBe('12 Innes Road');
});

it('offers "urgent — today" only for emergency services (AC12)', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);
    $painting = Service::query()->where('emergency_capable', false)->whereNull('requires_registration')->orderBy('id')->firstOrFail();
    $pro = Pro::factory()->approved()->create();
    $pro->services()->attach($painting);
    $pro->serviceAreas()->attach($property->suburb);

    describeJob(threadFor($this->leak), $this->leak)->call('selectProperty', $property->public_id)->assertSee('Urgent — today');
    describeJob(threadFor($painting), $painting)->call('selectProperty', $property->public_id)->assertSet('stage', 'when')->assertDontSee('Urgent — today')
        ->call('chooseWhen', 'today')->assertHasErrors(['when']);
});

it('uses Durban time for "today" (urgent bookings after midnight SAST)', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);
    // 23:30 UTC on the 10th is 01:30 SAST on the 11th.
    $this->travelTo(CarbonImmutable::parse('2026-11-10 23:30:00', 'UTC'));

    describeJob(threadFor($this->leak), $this->leak)->call('selectProperty', $property->public_id)
        ->call('chooseWhen', 'today')->call('finishPhotos')->assertSet('stage', 'summary')->assertSee('Urgent')
        ->call('confirmBooking')->assertHasNoErrors();

    expect(ServiceJob::query()->sole()->preferred_date->toDateString())->toBe('2026-11-11');
});

it('treats a tampered or out-of-range date as not chosen instead of an error page', function (string $date): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    describeJob(threadFor($this->leak), $this->leak)->call('selectProperty', $property->public_id)
        ->set('preferredDate', $date)->call('chooseWhen', 'morning')->assertHasErrors(['when'])->assertSet('stage', 'when');
})->with(['2026-99-99', 'yesterday', '2099-01-01']);

it('changes a section from the summary and comes straight back to it (AC15)', function (): void {
    [$customer, $property] = bookingCustomer();
    $other = Property::factory()->for($customer)->create(['label' => 'Flat', 'street_address' => '9 Other Road', 'suburb_id' => $property->suburb_id]);
    $this->actingAs($customer);

    $thread = bookUpToSummary(describeJob(threadFor($this->leak), $this->leak), $property);

    $thread->call('change', 'where')->assertSet('stage', 'where')
        ->call('selectProperty', $other->public_id)->assertSet('stage', 'summary')->assertSee('9 Other Road');
    $thread->call('change', 'answers')->assertSet('stage', 'questions')
        ->call('answer', 'leak_location', 'Toilet')->call('answer', 'severity', 'Steady flow')->assertSet('stage', 'summary')->assertSee('Toilet');
    $thread->call('change', 'notes')->assertSet('stage', 'notes')->set('notesDraft', 'Gate code 1234')->call('saveNotes')
        ->assertSet('stage', 'summary')->assertSee('Gate code 1234');
    $thread->call('change', 'when')->set('preferredDate', now()->addDays(5)->toDateString())->call('chooseWhen', 'afternoon')
        ->assertSet('stage', 'summary')->assertSee('Afternoon (12:00–17:00)');

    expect(ServiceJob::query()->sole()->property_id)->toBe($other->id);
});

it('drops the old answers when the service is changed from the summary', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    bookUpToSummary(describeJob(threadFor($this->leak), $this->leak), $property)
        ->call('change', 'service')->assertSet('stage', 'trade')->assertSet('serviceId', null)->assertSet('answers', [])
        ->assertSee('Your answers for the old service will be dropped');
});

it('enforces the notes limit', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    bookUpToSummary(describeJob(threadFor($this->leak), $this->leak), $property)
        ->call('change', 'notes')->set('notesDraft', str_repeat('a', 1001))->call('saveNotes')->assertHasErrors(['notesDraft']);

    expect(ServiceJob::query()->sole()->customer_notes)->toBeNull();
});

it('shows the posted message again on a second Confirm tap instead of an error', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    $thread = bookUpToSummary(describeJob(threadFor($this->leak), $this->leak), $property);
    $thread->call('confirmBooking')->call('confirmBooking')->assertHasNoErrors()->assertSet('stage', 'posted');

    expect(ServiceJob::query()->count())->toBe(1);
});

it('does not create a draft just by opening a service, and reuses a draft for the same service', function (): void {
    [$customer] = bookingCustomer();
    $this->actingAs($customer);

    threadFor($this->leak);
    expect(ServiceJob::query()->count())->toBe(0);

    threadFor($this->leak)->call('answer', 'leak_location', 'Tap');
    $draft = ServiceJob::query()->sole();

    session()->forget(Thread::SESSION_KEY);
    threadFor($this->leak)->call('answer', 'leak_location', 'Pipe')->assertSet('jobPublicId', $draft->public_id);
    expect(ServiceJob::query()->count())->toBe(1);
});

it('keeps an unfinished booking as a draft to resume in the thread (spec 005 AC12)', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    describeJob(threadFor($this->leak), $this->leak)->call('selectProperty', $property->public_id);
    $job = ServiceJob::query()->sole();

    Livewire::test(Home::class)->assertSee('Finish your request')->assertSee(route('booking.continue', $job));
    Livewire::test(Thread::class, ['job' => $job])->assertSet('stage', 'when')->assertSet('propertyPublicId', $property->public_id)
        ->assertSee('When do you need help?');
});

it('keeps the thread after a refresh and starts over on Restart (AC18)', function (): void {
    threadFor($this->leak)->call('answer', 'leak_location', 'Tap');

    threadFor($this->leak)->assertSet('answers', ['leak_location' => 'Tap'])->assertSee('How bad is it?')
        ->call('restart')->assertSet('serviceId', null)->assertSet('answers', [])->assertSet('stage', 'trade');
});

it('lets customers remove an unfinished request', function (): void {
    [$customer] = bookingCustomer();
    $this->actingAs($customer);
    threadFor($this->leak)->call('answer', 'leak_location', 'Tap');
    $draft = ServiceJob::query()->sole();

    Livewire::test(Home::class)->assertSee('Remove')->call('removeDraft', $draft->public_id)->assertDontSee('Finish your request');

    expect($draft->fresh()->status)->toBe(ServiceJobStatus::Cancelled)
        ->and(ServiceJobEvent::query()->sole()->actor_type)->toBe(ActorType::Customer);

    $other = ServiceJob::factory()->create(['service_id' => $this->leak->id]);
    Livewire::test(Home::class)->call('removeDraft', $other->public_id);
    expect($other->fresh()->status)->toBe(ServiceJobStatus::Draft);
});

it('lets customers tick several answers on a multi-choice question', function (): void {
    $damp = Service::query()->whereHas('questions', fn ($query) => $query->where('key', 'damp_where'))->sole();
    $thread = threadFor($damp);

    // Walk to the multi-choice question by answering the earlier ones.
    foreach ($damp->questions->takeUntil(fn ($question): bool => $question->key === 'damp_where') as $question) {
        $thread->call('answer', $question->key, match ($question->type->value) {
            'yes_no' => 'no',
            'multi_choice' => [$question->options[0]],
            default => $question->options[0],
        });
    }

    $thread->call('answer', 'damp_where', ['Ceiling', 'Bathroom', 42])->assertHasNoErrors()
        ->assertSet('answers.damp_where', ['Ceiling', 'Bathroom']);
});

it('lists my jobs and keeps other customers out (spec 005 AC13, AC14)', function (): void {
    [$customer, $property] = bookingCustomer();
    $mine = ServiceJob::factory()->open()->forProperty($property)->create(['service_id' => $this->leak->id]);
    $theirs = ServiceJob::factory()->open()->create(['service_id' => $this->leak->id]);
    $this->actingAs($customer);

    Livewire::test(Home::class)->assertSee('Leak repair')->assertSee('Finding your pros');
    $this->get(route('jobs.show', $mine))->assertOk();
    $this->get(route('jobs.show', $theirs))->assertNotFound();
    $this->get('/app/jobs/'.$mine->id)->assertNotFound();
    $this->get(route('booking.continue', $theirs))->assertNotFound();
    $this->get(route('booking.continue', $mine))->assertNotFound();
});

it('shows jobs to every admin role without street address or phone (spec 005 AC15)', function (Role $role): void {
    [, $property] = bookingCustomer();
    $job = ServiceJob::factory()->open()->forProperty($property)->create(['service_id' => $this->leak->id, 'customer_notes' => 'Behind the fridge']);
    $event = new ServiceJobEvent(['from_status' => 'draft', 'to_status' => 'open', 'event_type' => 'job_posted', 'actor_type' => 'customer', 'actor_id' => $job->customer_id]);
    $event->serviceJob()->associate($job)->save();
    $admin = User::factory()->create();
    $admin->assignRole($role->value);
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');

    Livewire::test(ListServiceJobs::class)->assertCanSeeTableRecords([$job])->assertDontSee('7 Private Lane')->assertDontSee($property->user->phone_e164);
    Livewire::test(ViewServiceJob::class, ['record' => $job->public_id])
        ->assertSee('Musgrave')->assertSee('Behind the fridge')->assertSee('Job posted')
        ->assertDontSee('7 Private Lane')->assertDontSee($property->user->phone_e164);

    expect($admin->can('update', $job))->toBeFalse()->and($admin->can('delete', $job))->toBeFalse();
})->with([Role::AdminSuper, Role::AdminSupport, Role::AdminVetting, Role::AdminFinance]);

it('keeps customers and pros out of the admin jobs list (spec 005 AC15)', function (): void {
    $this->actingAs(User::factory()->customer()->create())->get('/admin/service-jobs')->assertForbidden();
});

it('only accepts Sortd booking paths as a return address after adding a property', function (?string $return, ?string $expected): void {
    expect(BookingReturn::sanitise($return))->toBe($expected);
})->with([
    'booking start' => ['/book/plumbing/leak_repair', '/book/plumbing/leak_repair'],
    'draft continue' => ['/app/jobs/01HZX3V5Q8M2N4P6R8T0W2Y4A6/continue', '/app/jobs/01HZX3V5Q8M2N4P6R8T0W2Y4A6/continue'],
    'other site' => ['https://evil.example/book/a/b', null],
    'protocol relative' => ['//evil.example', null],
    'trailing newline' => ["/book/plumbing/leak_repair\n", null],
    'other page' => ['/app/properties', null],
    'missing' => [null, null],
]);

it('returns to the booking after adding a property from the properties page', function (): void {
    [$customer] = bookingCustomer();
    $this->actingAs($customer);

    Livewire::withQueryParams(['return' => '/book/plumbing/leak_repair'])->test(Form::class)
        ->set('label', 'Flat')->set('streetAddress', '3 Side Road')->call('selectSuburb', 'berea')->set('propertyType', 'flat')
        ->call('save')->assertRedirect('/book/plumbing/leak_repair');

    Livewire::withQueryParams(['return' => 'https://evil.example'])->test(Form::class)
        ->set('label', 'Office')->set('streetAddress', '4 Side Road')->call('selectSuburb', 'berea')->set('propertyType', 'business')
        ->call('save')->assertRedirect(route('properties.index'));
});

it('saves only the day that was checked, even if the calendar value is changed afterwards', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);
    $date = now()->addDays(3)->toDateString();

    bookUpToSummary(describeJob(threadFor($this->leak), $this->leak), $property, $date)
        ->set('preferredDate', '2020-01-01')->call('confirmBooking')->assertHasNoErrors();

    expect(ServiceJob::query()->sole()->preferred_date->toDateString())->toBe($date);
});

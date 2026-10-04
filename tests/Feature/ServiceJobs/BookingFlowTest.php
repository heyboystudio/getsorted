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
use App\Livewire\Booking\Wizard;
use App\Models\Pro;
use App\Models\Property;
use App\Models\ScopingQuestion;
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
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([CatalogueSeeder::class, SuburbSeeder::class]);
    $this->plumbing = Trade::query()->where('key', 'plumbing')->sole();
    $this->leak = Service::query()->where('key', 'leak_repair')->sole();
    $pro = Pro::factory()->approved()->create();
    $pro->services()->attach($this->leak);
    $pro->serviceAreas()->attach(Suburb::query()->where('slug', 'musgrave')->sole());
});

function bookingCustomer(): array
{
    $customer = User::factory()->customer()->create();
    $property = Property::factory()->for($customer)->create(['label' => 'Home', 'street_address' => '7 Private Lane', 'suburb_id' => Suburb::query()->where('slug', 'musgrave')->value('id')]);

    return [$customer, $property];
}

function startLeakBooking(): Testable
{
    $wizard = Livewire::test(Wizard::class, ['trade' => test()->plumbing, 'service' => test()->leak]);

    return $wizard->get('step') === 'coverage' ? $wizard->call('selectSuburb', 'musgrave')->call('next') : $wizard;
}

function answerLeakQuestions(Testable $wizard, string $severity = 'Dripping'): Testable
{
    return $wizard->call('choose', 'Tap')->call('choose', $severity);
}

it('shows active trades on the home page and services on a trade page (AC1)', function (): void {
    $this->get('/')->assertOk()->assertSee('What do you need help with?')->assertSee('Plumbing')->assertSee(route('trades.show', $this->plumbing));
    $this->get(route('trades.show', $this->plumbing))->assertOk()->assertSee('Leak repair')->assertSee(route('booking.start', [$this->plumbing, $this->leak]));

    $this->leak->update(['is_active' => false]);
    $this->get(route('trades.show', $this->plumbing))->assertDontSee('Leak repair');
    $this->get(route('booking.start', [$this->plumbing, $this->leak]))->assertNotFound();
});

it('asks questions one per screen, requires answers and goes back without losing them (AC3)', function (): void {
    [$customer] = bookingCustomer();
    $this->actingAs($customer);

    $wizard = startLeakBooking()->assertSee('Where is the leak coming from?')->assertDontSee('How bad is it?');
    $wizard->call('next')->assertHasErrors(['answer']);
    $wizard->call('choose', 'Tap')->assertSee('How bad is it?')->assertSet('questionIndex', 1);
    $wizard->call('back')->assertSet('questionIndex', 0)->assertSet('answers.leak_location', 'Tap');
    $wizard->call('choose', 'Not a real option')->assertHasErrors(['answer'])->assertSet('questionIndex', 0);
});

it('shows safety advice and marks the job urgent for an urgent answer (AC5)', function (): void {
    [$customer] = bookingCustomer();
    $this->actingAs($customer);

    $wizard = startLeakBooking()->call('choose', 'Pipe')->call('choose', 'Flooding');
    // Tapping an urgent answer stays on the question so the advice can be read.
    $wizard->assertSet('step', 'questions')->assertSee('This sounds urgent')->assertSee('close the main stopcock');
    $wizard->call('next')->assertSet('step', 'notes');

    expect(ServiceJob::query()->sole()->urgency)->toBe(Urgency::Urgent);
});

it('lets guests answer questions, then keeps the answers through login (AC2)', function (): void {
    $wizard = answerLeakQuestions(startLeakBooking())->call('next')->assertSet('step', 'photos')->call('next')->assertSet('step', 'property')->assertSee('Log in to continue');
    expect(ServiceJob::query()->count())->toBe(0);

    $wizard->call('logInToContinue')->assertRedirect(route('login'));
    expect(session('url.intended'))->toBe(route('booking.start', [$this->plumbing, $this->leak], false));

    [$customer] = bookingCustomer();
    $this->actingAs($customer);

    $resumed = startLeakBooking()->assertSet('step', 'property')->assertSet('answers.leak_location', 'Tap')->assertSet('answers.severity', 'Dripping');
    expect(ServiceJob::query()->sole()->scoping_answers)->toHaveKeys(['leak_location', 'severity']);
    $resumed->assertSee('Where is the work?');
});

it('books end to end: property, time, review, post and confirmation (AC6–AC9, AC11)', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);
    $date = now()->addDays(3)->toDateString();

    $wizard = answerLeakQuestions(startLeakBooking())
        ->set('notes', 'Leaks when the tap is open.')->call('next')
        ->call('next')
        ->call('next')->assertHasErrors(['property'])
        ->call('selectProperty', $property->public_id)->call('next')->assertSet('step', 'when')
        ->set('timeWindow', 'morning')->set('preferredDate', $date)->call('next')->assertSet('step', 'review')
        ->assertSee('7 Private Lane')->assertSee('Leaks when the tap is open.')->assertSee('Morning (07:00–12:00)');

    $job = ServiceJob::query()->sole();
    $wizard->call('post')->assertRedirect(route('jobs.show', $job));

    expect($job->fresh()->status)->toBe(ServiceJobStatus::Open)
        ->and(array_column($job->fresh()->orderedAnswers(), 'prompt'))->toBe(['Where is the leak coming from?', 'How bad is it?']);
    $this->withSession(['job_posted' => true])->get(route('jobs.show', $job))->assertOk()->assertSee('Your job is posted')->assertSee('Leak repair');
});

it('stops a booking for a property in a suburb Sortd is not in yet (AC6)', function (): void {
    [$customer] = bookingCustomer();
    $westville = Property::factory()->for($customer)->create(['suburb_id' => Suburb::query()->where('slug', 'westville')->value('id')]);
    $this->actingAs($customer);

    answerLeakQuestions(startLeakBooking())->call('next')->call('next')
        ->call('selectProperty', $westville->public_id)->call('next')
        ->assertSet('step', 'property')->assertSee('This property is in Westville')
        ->call('confirmPropertySuburb')->assertSet('step', 'waitlist')->assertSee('not available there');
});

it("cannot pick another customer's property (AC6)", function (): void {
    [$customer] = bookingCustomer();
    $theirs = Property::factory()->create();
    $this->actingAs($customer);

    answerLeakQuestions(startLeakBooking())->call('next')->call('next')
        ->call('selectProperty', $theirs->public_id)->assertSet('propertyPublicId', null);
});

it('offers "urgent — today" only for emergency services (AC7)', function (): void {
    [$customer] = bookingCustomer();
    $this->actingAs($customer);
    $coc = Service::query()->where('key', 'electrical_coc')->sole();

    startLeakBooking()->set('answers', ['leak_location' => 'Tap', 'severity' => 'Dripping'])->call('change', 'when')->assertSee('Urgent — today');
    Livewire::test(Wizard::class, ['trade' => $coc->trade, 'service' => $coc])->call('change', 'when')->assertDontSee('Urgent — today')
        ->set('timeWindow', 'today')->call('next')->assertHasErrors(['timeWindow']);
});

it('keeps an unfinished booking as a draft to resume from the account (AC12)', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    answerLeakQuestions(startLeakBooking())->call('next')->call('next')->call('selectProperty', $property->public_id)->call('next');
    $job = ServiceJob::query()->sole();

    Livewire::test(Home::class)->assertSee('Finish your request')->assertSee(route('booking.continue', $job));
    Livewire::test(Wizard::class, ['job' => $job])->assertSet('step', 'when')->assertSet('propertyPublicId', $property->public_id);
});

it('lists my jobs and keeps other customers out (AC13, AC14)', function (): void {
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

it('shows jobs to every admin role without street address or phone (AC15)', function (Role $role): void {
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

it('keeps customers and pros out of the admin jobs list (AC15)', function (): void {
    $this->actingAs(User::factory()->customer()->create())->get('/admin/service-jobs')->assertForbidden();
});

// --- Review fixes -------------------------------------------------------------------

it('lets customers tick several answers on a multi-choice question', function (): void {
    [$customer] = bookingCustomer();
    $this->actingAs($customer);
    $damp = Service::query()->where('key', 'damp_treatment')->first() ?? ScopingQuestion::query()->where('key', 'damp_where')->sole()->service;
    $pro = Pro::factory()->approved()->create();
    $pro->services()->attach($damp);
    $pro->serviceAreas()->attach(Suburb::query()->where('slug', 'musgrave')->sole());

    $wizard = Livewire::test(Wizard::class, ['trade' => $damp->trade, 'service' => $damp])
        ->call('selectSuburb', 'musgrave')->call('next');
    $index = $damp->questions->search(fn ($q): bool => $q->key === 'damp_where');
    $wizard->assertSet('answers.damp_where', []);

    // Walk to the multi-choice question by answering earlier ones with valid values.
    foreach ($damp->questions->take($index) as $earlier) {
        $wizard->set('answers.'.$earlier->key, $earlier->type->hasOptions() ? $earlier->options[0] : 'no')->call('next');
    }

    // What the browser does: checkbox values added to the array.
    $wizard->set('answers.damp_where', ['Ceiling', 'Bathroom'])->call('next')->assertHasNoErrors();
    expect(ServiceJob::query()->sole()->scoping_answers['damp_where']['answer'])->toBe(['Ceiling', 'Bathroom']);

    // A tampered non-array value is refused politely, not with an error page.
    $wizard->call('back')->set('answers.damp_where', true)->call('next')->assertHasErrors(['answer']);
});

it('does not create a draft just by opening a service, and reuses a draft for the same service', function (): void {
    [$customer] = bookingCustomer();
    $this->actingAs($customer);

    startLeakBooking();
    expect(ServiceJob::query()->count())->toBe(0);

    startLeakBooking()->call('choose', 'Tap');
    $draft = ServiceJob::query()->sole();

    startLeakBooking()->assertSet('jobPublicId', $draft->public_id)->assertSet('questionIndex', 0)->assertSet('answers.leak_location', 'Tap');
    expect(ServiceJob::query()->count())->toBe(1);
});

it('lets customers remove an unfinished request', function (): void {
    [$customer] = bookingCustomer();
    $this->actingAs($customer);
    startLeakBooking()->call('choose', 'Tap');
    $draft = ServiceJob::query()->sole();

    Livewire::test(Home::class)->assertSee('Remove')->call('removeDraft', $draft->public_id)->assertDontSee('Finish your request');

    expect($draft->fresh()->status)->toBe(ServiceJobStatus::Cancelled)
        ->and(ServiceJobEvent::query()->sole()->actor_type)->toBe(ActorType::Customer);

    $other = ServiceJob::factory()->create(['service_id' => $this->leak->id]);
    Livewire::test(Home::class)->call('removeDraft', $other->public_id);
    expect($other->fresh()->status)->toBe(ServiceJobStatus::Draft);
});

it('enforces the notes limit on every path', function (): void {
    [$customer] = bookingCustomer();
    $this->actingAs($customer);

    answerLeakQuestions(startLeakBooking())->set('notes', str_repeat('a', 1001))->call('change', 'when')->call('next')
        ->assertHasErrors();

    expect(ServiceJob::query()->sole()->customer_notes)->toBeNull();
});

it('uses Durban time for "today" (urgent bookings after midnight SAST)', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);
    // 23:30 UTC on the 10th is 01:30 SAST on the 11th.
    $this->travelTo(CarbonImmutable::parse('2026-11-10 23:30:00', 'UTC'));

    answerLeakQuestions(startLeakBooking())->call('next')->call('next')
        ->call('selectProperty', $property->public_id)->call('next')
        ->set('timeWindow', 'today')->call('next')->assertSet('step', 'review')->assertSee('Urgent')
        ->call('post')->assertHasNoErrors();

    expect(ServiceJob::query()->sole()->preferred_date->toDateString())->toBe('2026-11-11');
});

it('queues the job-posted message instead of sending it while the customer waits (AC11)', function (): void {
    Queue::fake();
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    answerLeakQuestions(startLeakBooking())->call('next')->call('next')->call('selectProperty', $property->public_id)->call('next')
        ->set('timeWindow', 'morning')->set('preferredDate', now()->addDays(2)->toDateString())->call('next')->call('post');

    Queue::assertPushedOn('notifications', SendJobPostedMessage::class);
    app(MessagingChannel::class)->assertNothingSent();
});

it('takes a second tap on "Post job" to the job instead of an error', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    $wizard = answerLeakQuestions(startLeakBooking())->call('next')->call('next')->call('selectProperty', $property->public_id)->call('next')
        ->set('timeWindow', 'morning')->set('preferredDate', now()->addDays(2)->toDateString())->call('next');
    $wizard->call('post');
    $job = ServiceJob::query()->sole();

    $wizard->call('post')->assertRedirect(route('jobs.show', $job))->assertHasNoErrors();
});

it('treats a tampered date as not chosen instead of an error page', function (): void {
    [$customer] = bookingCustomer();
    $this->actingAs($customer);

    answerLeakQuestions(startLeakBooking())->call('change', 'when')->set('timeWindow', 'morning')->set('preferredDate', '2026-99-99')
        ->call('next')->assertHasErrors(['preferredDate']);
});

it('sends a guest back into their booking after logging in (AC2)', function (): void {
    answerLeakQuestions(startLeakBooking())->call('next')->call('next')->call('logInToContinue');
    $customer = User::factory()->customer()->create(['phone_e164' => '+27821234567']);

    $login = Livewire::test(Login::class)->set('phone', '082 123 4567')->call('sendCode');
    $sent = app(MessagingChannel::class)->sent();
    $login->set('code', end($sent)->parameters['code'])->call('verifyCode')
        ->assertRedirect(route('booking.start', [$this->plumbing, $this->leak]));
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

it('returns to the booking after adding a property from it', function (): void {
    [$customer] = bookingCustomer();
    $this->actingAs($customer);

    Livewire::withQueryParams(['return' => '/book/plumbing/leak_repair'])->test(Form::class)
        ->set('label', 'Flat')->set('streetAddress', '3 Side Road')->call('selectSuburb', 'berea')->set('propertyType', 'flat')
        ->call('save')->assertRedirect('/book/plumbing/leak_repair');

    Livewire::withQueryParams(['return' => 'https://evil.example'])->test(Form::class)
        ->set('label', 'Office')->set('streetAddress', '4 Side Road')->call('selectSuburb', 'berea')->set('propertyType', 'business')
        ->call('save')->assertRedirect(route('properties.index'));
});

<?php

declare(strict_types=1);

use App\Domain\Matching\Actions\JoinWaitlist;
use App\Domain\Matching\EligibleProsQuery;
use App\Domain\ServiceJobs\Actions\PostServiceJob;
use App\Domain\ServiceJobs\Actions\SaveBookingDraft;
use App\Domain\ServiceJobs\Data\BookingData;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\TimeWindow;
use App\Domain\ServiceJobs\Exceptions\NoEligiblePros;
use App\Filament\Admin\Pages\WaitlistDemand;
use App\Livewire\Account\Home;
use App\Livewire\Booking\Thread;
use App\Models\Pro;
use App\Models\Property;
use App\Models\Service;
use App\Models\ServiceJob;
use App\Models\Suburb;
use App\Models\Trade;
use App\Models\User;
use App\Models\WaitlistEntry;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\SuburbSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([CatalogueSeeder::class, SuburbSeeder::class]);
    $this->trade = Trade::query()->where('key', 'plumbing')->sole();
    $this->service = Service::query()->where('key', 'leak_repair')->sole();
    $this->suburb = Suburb::query()->where('slug', 'musgrave')->sole();
});

it('checks coverage when the property is picked and sends an uncovered service to the waitlist', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    describeJob(threadFor($this->service), $this->service)
        ->call('selectProperty', $property->public_id)
        ->assertSet('stage', 'waitlist')->assertSet('propertyPublicId', null)
        ->assertSee('Yes, keep me updated');
});

it('throttles repeated coverage checks', function (): void {
    config()->set('sortd.waitlist.checks_per_hour', 1);
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    describeJob(threadFor($this->service), $this->service)->call('selectProperty', $property->public_id)->assertSet('stage', 'waitlist');
    session()->forget(Thread::SESSION_KEY);
    describeJob(threadFor($this->service), $this->service)->call('selectProperty', $property->public_id)
        ->assertHasErrors(['where'])->assertSet('stage', 'where');
});

it('continues to Where & when only for an eligible approved pro', function (): void {
    $pro = Pro::factory()->approved()->create();
    $pro->services()->attach($this->service);
    $pro->serviceAreas()->attach($this->suburb);
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    expect(app(EligibleProsQuery::class)->exists($this->service, $this->suburb))->toBeTrue();

    describeJob(threadFor($this->service), $this->service)->call('selectProperty', $property->public_id)
        ->assertSet('stage', 'when')->assertSee('When do you need help?');

    $pro->forceFill(['status' => 'suspended'])->save();
    expect(app(EligibleProsQuery::class)->exists($this->service, $this->suburb))->toBeFalse();
});

it('requires a current verified registration when the service needs one', function (): void {
    $registered = Service::query()->whereNotNull('requires_registration')->firstOrFail();
    $pro = Pro::factory()->approved()->create();
    $pro->services()->attach($registered);
    $pro->serviceAreas()->attach($this->suburb);

    expect(app(EligibleProsQuery::class)->exists($registered, $this->suburb))->toBeFalse();
    $document = $pro->documents()->forceCreate(['type' => $registered->requires_registration->value, 'status' => 'verified', 'verified_at' => now(), 'expires_at' => now()->addMonth()]);
    expect(app(EligibleProsQuery::class)->exists($registered, $this->suburb))->toBeTrue();
    $document->forceFill(['expires_at' => now()->subDay()])->save();
    expect(app(EligibleProsQuery::class)->exists($registered, $this->suburb))->toBeFalse();
});

it('excludes a pro at their weekly cap or with an upheld dispute against the customer', function (): void {
    $pro = Pro::factory()->approved()->create(['weekly_job_cap' => 1]);
    $pro->services()->attach($this->service);
    $pro->serviceAreas()->attach($this->suburb);
    $customer = User::factory()->customer()->create();
    $another = User::factory()->customer()->create();
    $job = ServiceJob::factory()->create(['service_id' => $this->service->id, 'customer_id' => $customer->id]);

    expect(app(EligibleProsQuery::class)->exists($this->service, $this->suburb, $customer))->toBeTrue();
    DB::table('pro_job_allocations')->insert(['pro_id' => $pro->id, 'service_job_id' => $job->id, 'allocated_at' => now()]);
    expect(app(EligibleProsQuery::class)->exists($this->service, $this->suburb, $customer))->toBeFalse();

    DB::table('pro_job_allocations')->delete();
    DB::table('pro_customer_exclusions')->insert(['pro_id' => $pro->id, 'customer_id' => $customer->id, 'service_job_id' => $job->id, 'upheld_at' => now()]);
    expect(app(EligibleProsQuery::class)->exists($this->service, $this->suburb, $customer))->toBeFalse()
        ->and(app(EligibleProsQuery::class)->exists($this->service, $this->suburb, $another))->toBeTrue();
});

it('stores one private waitlist entry for a repeated submission from the thread', function (): void {
    [$customer, $property] = bookingCustomer();
    $customer->forceFill(['phone_e164' => '+27659107772'])->save();
    $this->actingAs($customer);

    foreach ([1, 2] as $attempt) {
        session()->forget(Thread::SESSION_KEY);
        describeJob(threadFor($this->service), $this->service)->call('selectProperty', $property->public_id)
            ->call('joinWaitlist')->assertSet('stage', 'closed');
    }

    expect(WaitlistEntry::query()->count())->toBe(1)
        ->and(WaitlistEntry::query()->sole()->phone_e164)->toBe('+27659107772');
});

it('answers a throttled visitor the same way whether or not the phone is already waitlisted', function (): void {
    config()->set('sortd.waitlist.submissions_per_ip_hour', 1);
    $join = fn (string $phone) => app(JoinWaitlist::class)->handle($this->service, $this->suburb, '', 'Andy', $phone, true, '10.0.0.1');

    $join('065 910 7772');

    expect(fn () => $join('065 910 7772'))->toThrow(ValidationException::class, 'Please try again later.');
    expect(fn () => $join('071 234 5678'))->toThrow(ValidationException::class, 'Please try again later.');
    expect(WaitlistEntry::query()->count())->toBe(1);
});

it('shows a throttled waitlist tap as a message in the thread', function (): void {
    config()->set('sortd.waitlist.submissions_per_ip_hour', 1);
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);
    app(JoinWaitlist::class)->handle($this->service, $this->suburb, '', 'Sam', '071 234 5678', true, '127.0.0.1');

    describeJob(threadFor($this->service), $this->service)->call('selectProperty', $property->public_id)
        ->call('joinWaitlist')->assertHasErrors(['waitlist'])->assertSet('stage', 'waitlist');
    expect(WaitlistEntry::query()->count())->toBe(1);
});

it('rejects address-like suburb text and never displays an unlisted suburb to admins', function (): void {
    $join = fn (string $suburb) => app(JoinWaitlist::class)->handle($this->service, null, $suburb, 'Andy', '065 910 7772', true, '10.0.0.1');

    expect(fn () => $join('7 Private Lane'))->toThrow(ValidationException::class);
    expect(WaitlistEntry::query()->count())->toBe(0);

    $join('Outer Village');
    $page = new WaitlistDemand;
    $demand = $page->demand();
    expect($demand->sole()->suburb_name)->toBe('Other suburb')
        ->and($demand->sole()->suburb_name)->not->toBe('Outer Village');
});

it('requires valid contact and consent', function (): void {
    $join = fn (string $phone, bool $consent) => app(JoinWaitlist::class)->handle($this->service, $this->suburb, '', 'Andy', $phone, $consent, '10.0.0.1');

    try {
        $join('not a phone', false);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKeys(['phone', 'consent']);
    }

    expect(WaitlistEntry::query()->count())->toBe(0);
    $join('065 910 7772', true);
    expect(WaitlistEntry::query()->count())->toBe(1);
});

it('throttles repeated waitlist submissions from one visitor', function (): void {
    config()->set('sortd.waitlist.submissions_per_ip_hour', 1);
    app(JoinWaitlist::class)->handle($this->service, $this->suburb, '', 'Andy', '065 910 7772', true, '10.0.0.1');

    expect(fn () => app(JoinWaitlist::class)->handle($this->service, $this->suburb, '', 'Sam', '071 234 5678', true, '10.0.0.1'))
        ->toThrow(ValidationException::class);
    expect(WaitlistEntry::query()->count())->toBe(1);
});

it('refuses posting when the last eligible pro becomes unavailable', function (): void {
    $customer = User::factory()->customer()->create();
    $property = Property::factory()->for($customer)->create(['suburb_id' => $this->suburb->id]);
    $pro = Pro::factory()->approved()->create();
    $pro->services()->attach($this->service);
    $pro->serviceAreas()->attach($this->suburb);
    $answers = [];
    foreach ($this->service->questions as $question) {
        $answers[$question->key] = ['prompt' => $question->prompt, 'type' => $question->type->value, 'answer' => $question->options[0]];
    }
    $job = app(SaveBookingDraft::class)->handle($customer, $this->service, null, new BookingData(
        answers: $answers, notes: null, propertyPublicId: $property->public_id,
        preferredDate: now()->toImmutable()->addDays(2), timeWindow: TimeWindow::Morning,
    ));
    $pro->forceFill(['status' => 'suspended'])->save();

    expect(fn () => app(PostServiceJob::class)->handle($customer, $job))->toThrow(NoEligiblePros::class);
    expect($job->fresh()->status)->toBe(ServiceJobStatus::Draft);
});

it('keeps waitlist demand inside the admin panel and out of customer views', function (): void {
    WaitlistEntry::factory()->create(['service_id' => $this->service->id, 'first_name' => 'Private Name', 'phone_e164' => '+27659107772']);
    $customer = User::factory()->customer()->create();
    $this->actingAs($customer)->get('/admin/waitlist-demand')->assertForbidden();
    $this->assertFalse(WaitlistDemand::canAccess());
    $row = (new WaitlistDemand)->demand()->sole();
    expect($row->getAttributes())->not->toHaveKeys(['first_name', 'phone_e164', 'suburb_text']);
});

it('lets a verified customer remove only waitlist requests for their phone', function (): void {
    $customer = User::factory()->customer()->create(['phone_e164' => '+27659107772']);
    $own = WaitlistEntry::factory()->create(['service_id' => $this->service->id, 'phone_e164' => $customer->phone_e164]);
    $other = WaitlistEntry::factory()->create(['service_id' => $this->service->id]);

    $this->actingAs($customer);
    Livewire::test(Home::class)->assertSee('Remove my waitlist requests')->call('removeWaitlistRequests')->assertSee('were removed');

    expect(WaitlistEntry::query()->find($own->id))->toBeNull()
        ->and(WaitlistEntry::query()->find($other->id))->not->toBeNull();
});

it('prunes waitlist entries after twelve months', function (): void {
    $old = WaitlistEntry::factory()->create(['created_at' => now()->subMonths(13)]);
    $recent = WaitlistEntry::factory()->create(['created_at' => now()->subMonths(2)]);

    $this->artisan('model:prune', ['--model' => [WaitlistEntry::class]])->assertSuccessful();
    expect(WaitlistEntry::query()->find($old->id))->toBeNull()
        ->and(WaitlistEntry::query()->find($recent->id))->not->toBeNull();
});

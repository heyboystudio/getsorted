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
use App\Models\Property;
use App\Models\ServiceJob;
use App\Models\User;
use App\Models\WaitlistEntry;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
    $this->trade = tradeOf('plumbing');
    $this->point = durban();
});

it('checks coverage when the address is picked and sends an uncovered trade to the waitlist', function (): void {
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    describeJob(threadFor($this->trade), $this->trade)
        ->call('selectProperty', $property->public_id)
        ->assertSet('stage', 'waitlist')->assertSet('propertyPublicId', null)
        ->assertSee('Yes, keep me updated');
});

it('throttles repeated coverage checks', function (): void {
    config()->set('getsorted.waitlist.checks_per_hour', 1);
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    describeJob(threadFor($this->trade), $this->trade)->call('selectProperty', $property->public_id)->assertSet('stage', 'waitlist');
    session()->forget(Thread::SESSION_KEY);
    describeJob(threadFor($this->trade), $this->trade)->call('selectProperty', $property->public_id)
        ->assertHasErrors(['where'])->assertSet('stage', 'where');
});

it('continues to the date only when an eligible approved pro is within range', function (): void {
    $pro = proNear(['plumbing'], 3);
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);

    expect(app(EligibleProsQuery::class)->exists($this->trade, $this->point))->toBeTrue();

    describeJob(threadFor($this->trade), $this->trade)->call('selectProperty', $property->public_id)
        ->assertSet('stage', 'when')->assertSee('When do you need help?');

    $pro->forceFill(['status' => 'suspended'])->save();
    expect(app(EligibleProsQuery::class)->exists($this->trade, $this->point))->toBeFalse();
});

it('does not count a pro of another trade or without a base address', function (): void {
    proNear(['electrical'], 1);
    $noBase = proNear(['plumbing'], 1);
    $noBase->forceFill(['base_location' => null])->save();

    expect(app(EligibleProsQuery::class)->exists($this->trade, $this->point))->toBeFalse();
});

it('never gates on registration: unverified pros are eligible, verified ones are badged (spec 020, decision 2)', function (): void {
    $electrical = tradeOf('electrical');
    $pro = proNear(['electrical'], 1);

    expect(app(EligibleProsQuery::class)->exists($electrical, $this->point))->toBeTrue()
        ->and($pro->load('documents')->isVerifiedFor($electrical))->toBeFalse();

    $document = $pro->documents()->forceCreate(['type' => $electrical->registration->value, 'status' => 'verified', 'verified_at' => now(), 'expires_at' => now()->addMonth()]);
    expect($pro->refresh()->load('documents')->isVerifiedFor($electrical))->toBeTrue();

    $document->forceFill(['expires_at' => now()->subDay()])->save();
    expect($pro->refresh()->load('documents')->isVerifiedFor($electrical))->toBeFalse()
        ->and(app(EligibleProsQuery::class)->exists($electrical, $this->point))->toBeTrue();
});

it('excludes a pro at their weekly cap or with an upheld dispute against the customer', function (): void {
    $pro = proNear(['plumbing'], 1);
    $pro->forceFill(['weekly_job_cap' => 1])->save();
    $customer = User::factory()->customer()->create();
    $another = User::factory()->customer()->create();
    $job = ServiceJob::factory()->create(['trade_id' => $this->trade->id, 'customer_id' => $customer->id]);
    $query = app(EligibleProsQuery::class);

    expect($query->exists($this->trade, $this->point, $customer))->toBeTrue();
    DB::table('pro_job_allocations')->insert(['pro_id' => $pro->id, 'service_job_id' => $job->id, 'allocated_at' => now()]);
    expect($query->exists($this->trade, $this->point, $customer))->toBeFalse();

    DB::table('pro_job_allocations')->delete();
    DB::table('pro_customer_exclusions')->insert(['pro_id' => $pro->id, 'customer_id' => $customer->id, 'service_job_id' => $job->id, 'upheld_at' => now()]);
    expect($query->exists($this->trade, $this->point, $customer))->toBeFalse()
        ->and($query->exists($this->trade, $this->point, $another))->toBeTrue();
});

it('stores one private waitlist entry for a repeated submission from the thread', function (): void {
    [$customer, $property] = bookingCustomer();
    $customer->forceFill(['phone_e164' => '+27659107772'])->save();
    $this->actingAs($customer);

    foreach ([1, 2] as $attempt) {
        session()->forget(Thread::SESSION_KEY);
        describeJob(threadFor($this->trade), $this->trade)->call('selectProperty', $property->public_id)
            ->call('joinWaitlist')->assertSet('stage', 'closed');
    }

    expect(WaitlistEntry::query()->count())->toBe(1)
        ->and(WaitlistEntry::query()->sole()->phone_e164)->toBe('+27659107772');
});

it('stores the waitlist request by trade, area and point, never a street address', function (): void {
    app(JoinWaitlist::class)->handle($this->trade, 'Musgrave', $this->point, 'Andy', '065 910 7772', true, '10.0.0.1');

    $entry = WaitlistEntry::query()->sole();
    expect($entry->trade_id)->toBe($this->trade->id)->and($entry->area_label)->toBe('Musgrave')
        ->and(DB::table('waitlist_entries')->whereNotNull('location')->count())->toBe(1);
});

it('answers a throttled visitor the same way whether or not the phone is already waitlisted', function (): void {
    config()->set('getsorted.waitlist.submissions_per_ip_hour', 1);
    $join = fn (string $phone) => app(JoinWaitlist::class)->handle($this->trade, 'Musgrave', $this->point, 'Andy', $phone, true, '10.0.0.1');

    $join('065 910 7772');

    expect(fn () => $join('065 910 7772'))->toThrow(ValidationException::class, 'Please try again later.');
    expect(fn () => $join('071 234 5678'))->toThrow(ValidationException::class, 'Please try again later.');
    expect(WaitlistEntry::query()->count())->toBe(1);
});

it('shows a throttled waitlist tap as a message in the thread', function (): void {
    config()->set('getsorted.waitlist.submissions_per_ip_hour', 1);
    [$customer, $property] = bookingCustomer();
    $this->actingAs($customer);
    app(JoinWaitlist::class)->handle($this->trade, 'Musgrave', $this->point, 'Sam', '071 234 5678', true, '127.0.0.1');

    describeJob(threadFor($this->trade), $this->trade)->call('selectProperty', $property->public_id)
        ->call('joinWaitlist')->assertHasErrors(['waitlist'])->assertSet('stage', 'waitlist');
    expect(WaitlistEntry::query()->count())->toBe(1);
});

it('requires valid contact and consent', function (): void {
    $join = fn (string $phone, bool $consent) => app(JoinWaitlist::class)->handle($this->trade, 'Musgrave', $this->point, 'Andy', $phone, $consent, '10.0.0.1');

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
    config()->set('getsorted.waitlist.submissions_per_ip_hour', 1);
    app(JoinWaitlist::class)->handle($this->trade, 'Musgrave', $this->point, 'Andy', '065 910 7772', true, '10.0.0.1');

    expect(fn () => app(JoinWaitlist::class)->handle($this->trade, 'Musgrave', $this->point, 'Sam', '071 234 5678', true, '10.0.0.1'))
        ->toThrow(ValidationException::class);
    expect(WaitlistEntry::query()->count())->toBe(1);
});

it('refuses posting when the last eligible pro becomes unavailable', function (): void {
    $customer = User::factory()->customer()->create();
    $property = Property::factory()->for($customer)->create();
    $pro = proNear(['plumbing'], 2);
    $job = app(SaveBookingDraft::class)->handle($customer, $this->trade, null, new BookingData(
        facts: [['id' => 'f1', 'text' => 'tap drips', 'turn' => 1]], notes: null, propertyPublicId: $property->public_id,
        preferredDate: now()->toImmutable()->addDays(2), timeWindow: TimeWindow::Morning,
    ));
    $pro->forceFill(['status' => 'suspended'])->save();

    expect(fn () => app(PostServiceJob::class)->handle($customer, $job))->toThrow(NoEligiblePros::class);
    expect($job->fresh()->status)->toBe(ServiceJobStatus::Draft);
});

it('keeps waitlist demand inside the admin panel and out of customer views', function (): void {
    WaitlistEntry::factory()->create(['trade_id' => $this->trade->id, 'first_name' => 'Private Name', 'phone_e164' => '+27659107772']);
    $customer = User::factory()->customer()->create();
    $this->actingAs($customer)->get('/admin/waitlist-demand')->assertForbidden();
    $this->assertFalse(WaitlistDemand::canAccess());
    $row = (new WaitlistDemand)->demand()->sole();
    expect($row->getAttributes())->not->toHaveKeys(['first_name', 'phone_e164', 'area_label'])
        ->and($row->area_name)->toBe('Musgrave')->and($row->trade_name)->toBe('Plumbing');
});

it('lets a verified customer remove only waitlist requests for their phone', function (): void {
    $customer = User::factory()->customer()->create(['phone_e164' => '+27659107772']);
    $own = WaitlistEntry::factory()->create(['trade_id' => $this->trade->id, 'phone_e164' => $customer->phone_e164]);
    $other = WaitlistEntry::factory()->create(['trade_id' => $this->trade->id]);

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

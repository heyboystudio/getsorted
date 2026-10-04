<?php

declare(strict_types=1);

use App\Domain\Matching\EligibleProsQuery;
use App\Domain\ServiceJobs\Actions\PostServiceJob;
use App\Domain\ServiceJobs\Actions\SaveBookingDraft;
use App\Domain\ServiceJobs\Data\BookingData;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\TimeWindow;
use App\Domain\ServiceJobs\Exceptions\NoEligiblePros;
use App\Filament\Admin\Pages\WaitlistDemand;
use App\Livewire\Account\Home;
use App\Livewire\Booking\Wizard;
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
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([CatalogueSeeder::class, SuburbSeeder::class]);
    $this->trade = Trade::query()->where('key', 'plumbing')->sole();
    $this->service = Service::query()->where('key', 'leak_repair')->sole();
    $this->suburb = Suburb::query()->where('slug', 'musgrave')->sole();
});

it('starts with a suburb check and sends an uncovered service to the waitlist', function (): void {
    Livewire::test(Wizard::class, ['trade' => $this->trade, 'service' => $this->service])
        ->assertSet('step', 'coverage')
        ->assertSee('Where do you need help?')
        ->call('selectSuburb', $this->suburb->slug)
        ->call('next')
        ->assertSet('step', 'waitlist')
        ->assertDontSee('Where is the leak coming from?');
});

it('throttles repeated coverage checks', function (): void {
    config()->set('sortd.waitlist.checks_per_hour', 1);
    $wizard = Livewire::test(Wizard::class, ['trade' => $this->trade, 'service' => $this->service])
        ->call('selectSuburb', $this->suburb->slug)->call('next')->assertSet('step', 'waitlist');
    $wizard->call('back')->call('next')->assertHasErrors(['suburbQuery']);
});

it('continues to questions only for an eligible approved pro', function (): void {
    $pro = Pro::factory()->approved()->create();
    $pro->services()->attach($this->service);
    $pro->serviceAreas()->attach($this->suburb);

    expect(app(EligibleProsQuery::class)->exists($this->service, $this->suburb))->toBeTrue();

    Livewire::test(Wizard::class, ['trade' => $this->trade, 'service' => $this->service])
        ->call('selectSuburb', $this->suburb->slug)
        ->call('next')
        ->assertSet('step', 'questions')
        ->assertSee('Where is the leak coming from?');

    $pro->update(['status' => 'paused']);
    expect(app(EligibleProsQuery::class)->exists($this->service, $this->suburb))->toBeFalse();
});

it('requires a current verified registration when the service needs one', function (): void {
    $registered = Service::query()->whereNotNull('requires_registration')->firstOrFail();
    $pro = Pro::factory()->approved()->create();
    $pro->services()->attach($registered);
    $pro->serviceAreas()->attach($this->suburb);

    expect(app(EligibleProsQuery::class)->exists($registered, $this->suburb))->toBeFalse();
    $document = $pro->documents()->create(['type' => $registered->requires_registration->value, 'status' => 'verified', 'verified_at' => now(), 'expires_at' => now()->addMonth()]);
    expect(app(EligibleProsQuery::class)->exists($registered, $this->suburb))->toBeTrue();
    $document->update(['expires_at' => now()->subDay()]);
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

it('stores one private waitlist entry for a repeated submission', function (): void {
    Livewire::test(Wizard::class, ['trade' => $this->trade, 'service' => $this->service])
        ->call('selectSuburb', $this->suburb->slug)->call('next')
        ->set('waitlistFirstName', 'Andy')
        ->set('waitlistPhone', '065 910 7772')
        ->set('waitlistConsent', true)
        ->call('joinWaitlist')
        ->assertSet('step', 'waitlist_done');

    Livewire::test(Wizard::class, ['trade' => $this->trade, 'service' => $this->service])
        ->call('selectSuburb', $this->suburb->slug)->call('next')
        ->set('waitlistFirstName', 'Andy')->set('waitlistPhone', '065 910 7772')
        ->set('waitlistConsent', true)->call('joinWaitlist')->assertSet('step', 'waitlist_done');
    expect(WaitlistEntry::query()->count())->toBe(1)
        ->and(WaitlistEntry::query()->sole()->phone_e164)->toBe('+27659107772');
});

it('answers a throttled visitor the same way whether or not the phone is already waitlisted', function (): void {
    config()->set('sortd.waitlist.submissions_per_ip_hour', 1);
    $submit = fn (string $phone) => Livewire::test(Wizard::class, ['trade' => $this->trade, 'service' => $this->service])
        ->call('selectSuburb', $this->suburb->slug)->call('next')
        ->set('waitlistFirstName', 'Andy')->set('waitlistPhone', $phone)
        ->set('waitlistConsent', true)->call('joinWaitlist');

    $submit('065 910 7772')->assertHasNoErrors();

    $submit('065 910 7772')->assertHasErrors(['waitlist'])->assertSet('step', 'waitlist');
    $submit('071 234 5678')->assertHasErrors(['waitlist'])->assertSet('step', 'waitlist');
    expect(WaitlistEntry::query()->count())->toBe(1);
});

it('rejects address-like suburb text and never displays an unlisted suburb to admins', function (): void {
    $wizard = Livewire::test(Wizard::class, ['trade' => $this->trade, 'service' => $this->service])
        ->set('suburbQuery', '7 Private Lane')->call('next')
        ->set('waitlistFirstName', 'Andy')->set('waitlistPhone', '065 910 7772')
        ->set('waitlistConsent', true)->call('joinWaitlist')->assertHasErrors(['suburb']);
    expect(WaitlistEntry::query()->count())->toBe(0);

    $wizard->call('back')->set('suburbQuery', 'Outer Village')->call('next')
        ->call('joinWaitlist')->assertHasNoErrors()->assertSet('step', 'waitlist_done');
    $page = new WaitlistDemand;
    $demand = $page->demand();
    expect($demand->sole()->suburb_name)->toBe('Other suburb')
        ->and($demand->sole()->suburb_name)->not->toBe('Outer Village');
});

it('requires valid contact and consent', function (): void {
    $wizard = Livewire::test(Wizard::class, ['trade' => $this->trade, 'service' => $this->service])
        ->call('selectSuburb', $this->suburb->slug)->call('next')
        ->set('waitlistFirstName', 'Andy')->set('waitlistPhone', 'not a phone')
        ->call('joinWaitlist')->assertHasErrors(['phone', 'consent']);

    expect(WaitlistEntry::query()->count())->toBe(0);

    $wizard->set('waitlistPhone', '065 910 7772')->set('waitlistConsent', true);
    $wizard->call('joinWaitlist')->assertHasNoErrors();
    expect(WaitlistEntry::query()->count())->toBe(1);
});

it('throttles repeated waitlist submissions from one visitor', function (): void {
    config()->set('sortd.waitlist.submissions_per_ip_hour', 1);
    $wizard = Livewire::test(Wizard::class, ['trade' => $this->trade, 'service' => $this->service])
        ->call('selectSuburb', $this->suburb->slug)->call('next')
        ->set('waitlistFirstName', 'Andy')->set('waitlistPhone', '065 910 7772')
        ->set('waitlistConsent', true)->call('joinWaitlist')->assertHasNoErrors();

    Livewire::test(Wizard::class, ['trade' => $this->trade, 'service' => $this->service])
        ->call('selectSuburb', $this->suburb->slug)->call('next')
        ->set('waitlistFirstName', 'Sam')->set('waitlistPhone', '071 234 5678')
        ->set('waitlistConsent', true)->call('joinWaitlist')->assertHasErrors(['waitlist']);
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
    $pro->update(['status' => 'paused']);

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

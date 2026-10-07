<?php

declare(strict_types=1);

use App\Domain\Accounts\Enums\Role;
use App\Domain\Pros\Enums\DocumentType;
use App\Domain\Quotes\Actions\AcceptQuote;
use App\Domain\Quotes\Actions\ReviseQuote;
use App\Domain\Quotes\Actions\SubmitQuote;
use App\Domain\Quotes\Actions\WithdrawQuote;
use App\Domain\Quotes\Data\QuoteDraft;
use App\Domain\Quotes\Data\QuoteLineData;
use App\Domain\Quotes\Enums\LineKind;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Domain\ServiceJobs\Actions\PostServiceJob;
use App\Domain\ServiceJobs\Actions\SaveBookingDraft;
use App\Domain\ServiceJobs\Data\BookingData;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\TimeWindow;
use App\Filament\Admin\Resources\ServiceJobs\Pages\ViewServiceJob;
use App\Filament\Admin\Resources\ServiceJobs\RelationManagers\QuotesRelationManager;
use App\Livewire\Account\Jobs\Show as CustomerJob;
use App\Livewire\Pros\Jobs\Show as ProJob;
use App\Models\Pro;
use App\Models\Property;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogueSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
    Storage::fake('media');
});

function screenQuotingPro(string $name = 'Dlamini Plumbing'): Pro
{
    $pro = proNear(['plumbing'], 2);
    $pro->forceFill(['business_name' => $name])->save();

    return $pro;
}

function screenQuoteJob(): ServiceJob
{
    $customer = User::factory()->customer()->create(['first_name' => 'Nomvula', 'last_name' => 'Secretname', 'phone_e164' => '+27829990000']);
    $property = Property::factory()->for($customer)->create(['street_address' => '7 Private Lane', 'label' => 'Home']);
    $draft = app(SaveBookingDraft::class)->handle($customer, tradeOf('plumbing'), null, new BookingData(
        facts: [['id' => 'f1', 'text' => 'tap drips when fully closed', 'turn' => 1]], notes: 'Under the sink.', propertyPublicId: $property->public_id,
        preferredDate: now()->toImmutable()->addDays(2), timeWindow: TimeWindow::Morning,
    ));

    return app(PostServiceJob::class)->handle($customer, $draft);
}

function screenInvite(ServiceJob $job, Pro $pro): ServiceJobInvite
{
    $invite = ServiceJobInvite::query()->where('service_job_id', $job->id)->where('pro_id', $pro->id)->sole();

    // Pros accept the job before they can quote; most screens here start from that point.
    if ($invite->status->isOpen()) {
        $invite->forceFill(['status' => 'accepted'])->save();
    }

    return $invite->refresh();
}

function screenSubmit(ServiceJob $job, Pro $pro, int $deposit = 0): Quote
{
    return app(SubmitQuote::class)->handle($pro->user, screenInvite($job, $pro), new QuoteDraft(
        [new QuoteLineData(LineKind::Labour, 'Replace washer', '1', 45000), new QuoteLineData(LineKind::Materials, 'Washer kit', '1', 12050)],
        $deposit, CarbonImmutable::now('Africa/Johannesburg')->addDay()->startOfDay(), 7, 'Can come tomorrow morning.',
    ));
}

// --- Pro builder (AC1–AC3, AC5) ----------------------------------------------------------

it('builds, previews and sends a quote from the invite page (AC1–AC3)', function (): void {
    $pro = screenQuotingPro();
    $job = screenQuoteJob();
    $this->actingAs($pro->user);

    Livewire::test(ProJob::class, ['invite' => screenInvite($job, $pro)])
        ->call('startQuote')
        ->set('lines.0.kind', 'labour')->set('lines.0.description', 'Replace washer')->set('lines.0.quantity', '1.5')->set('lines.0.unitPrice', '300')
        ->call('addLine')
        ->set('lines.1.kind', 'materials')->set('lines.1.description', 'Washer kit')->set('lines.1.quantity', '1')->set('lines.1.unitPrice', '120.50')
        ->set('depositPercent', 20)->set('earliestStartDate', now('Africa/Johannesburg')->addDay()->toDateString())->set('validityDays', 7)
        ->call('preview')->assertHasNoErrors()
        ->assertSee('R 570.50')->assertSee('R 114.10')->assertSee('Estimated payout')->assertSee('R 516.50')
        ->call('submitQuote')->assertHasNoErrors()
        ->assertSee('Quote sent');

    $quote = Quote::query()->sole();
    expect($quote->total_cents)->toBe(57050)->and($quote->deposit_cents)->toBe(11410)->and($quote->lines)->toHaveCount(2);
});

it('shows field errors instead of saving a bad quote (AC1)', function (): void {
    $pro = screenQuotingPro();
    $job = screenQuoteJob();
    $this->actingAs($pro->user);

    Livewire::test(ProJob::class, ['invite' => screenInvite($job, $pro)])
        ->call('startQuote')
        ->set('lines.0.kind', 'labour')->set('lines.0.description', '')->set('lines.0.quantity', '0')->set('lines.0.unitPrice', 'abc')
        ->call('preview')->assertHasErrors(['lines.0.description', 'lines.0.quantity', 'lines.0.unitPrice']);

    expect(Quote::query()->count())->toBe(0);
});

it('lets the pro revise or withdraw a sent quote from the invite page (AC5)', function (): void {
    $pro = screenQuotingPro();
    $job = screenQuoteJob();
    $quote = screenSubmit($job, $pro);
    $this->actingAs($pro->user);

    Livewire::test(ProJob::class, ['invite' => screenInvite($job, $pro)])
        ->assertSee('Your quote')->assertSee('R 570.50')
        ->call('startRevision')->assertSet('lines.0.description', 'Replace washer')
        ->set('lines.0.unitPrice', '400')->call('preview')->call('submitQuote')->assertHasNoErrors();

    expect($quote->fresh()->status)->toBe(QuoteStatus::Superseded)
        ->and(Quote::query()->where('status', QuoteStatus::Submitted)->sole()->total_cents)->toBe(52050);

    Livewire::test(ProJob::class, ['invite' => screenInvite($job, $pro)])
        ->set('withdrawReason', 'Fully booked.')->call('withdraw')->assertHasNoErrors();
    expect(Quote::query()->where('status', QuoteStatus::Withdrawn)->count())->toBe(1);
});

it('tells a pro politely when the job is already full (AC4)', function (): void {
    $pros = array_map(fn (string $name): Pro => screenQuotingPro($name), ['A', 'B', 'C', 'D', 'E', 'F']);
    $job = screenQuoteJob();
    foreach (array_slice($pros, 0, 5) as $pro) {
        screenSubmit($job, $pro);
    }
    $this->actingAs($pros[5]->user);

    Livewire::test(ProJob::class, ['invite' => screenInvite($job, $pros[5])])->assertSee('This job is full')->assertDontSee('Send an estimate');
});

it('sends a quote once even when "Send quote" is tapped twice (AC3)', function (): void {
    $pro = screenQuotingPro();
    $job = screenQuoteJob();
    $this->actingAs($pro->user);

    Livewire::test(ProJob::class, ['invite' => screenInvite($job, $pro)])
        ->call('startQuote')
        ->set('lines.0.kind', 'labour')->set('lines.0.description', 'Replace washer')->set('lines.0.quantity', '1')->set('lines.0.unitPrice', '300')
        ->call('preview')->call('submitQuote')->call('submitQuote')->assertHasNoErrors();

    expect(Quote::query()->count())->toBe(1)->and(Quote::query()->sole()->version)->toBe(1);
});

it('previews masked text, notes, subtotals and dates exactly as the customer will see them (AC2, AC6)', function (): void {
    $pro = screenQuotingPro();
    $job = screenQuoteJob();
    $this->actingAs($pro->user);

    Livewire::test(ProJob::class, ['invite' => screenInvite($job, $pro)])
        ->call('startQuote')
        ->set('lines.0.kind', 'labour')->set('lines.0.description', 'Call 082 123 4567')->set('lines.0.quantity', '1')->set('lines.0.unitPrice', '120,50')
        ->set('notes', 'Mail me@example.com, free on Monday')->set('validityDays', 7)
        ->call('preview')->assertHasNoErrors()
        ->assertSee('Call [phone]')->assertDontSee('082 123 4567')->assertDontSee('me@example.com')->assertSee('free on Monday')
        ->assertSee('R 120.50')->assertSee('Labour')->assertSee('Valid until')->assertSee('Earliest start')
        // The customer's own notes still show, separate from the pro's quote notes.
        ->assertSee("Customer's notes")->assertSee('Under the sink.');
});

it('shows why a withdrawal failed instead of doing nothing (AC5)', function (): void {
    $pro = screenQuotingPro();
    $job = screenQuoteJob();
    $quote = screenSubmit($job, $pro);
    $this->actingAs($pro->user);

    Livewire::test(ProJob::class, ['invite' => screenInvite($job, $pro)])
        ->set('withdrawReason', '  ')->call('withdraw')->assertHasErrors('withdrawReason')->assertSee('Tell the customer briefly why.');

    expect($quote->fresh()->status)->toBe(QuoteStatus::Submitted);
});

// --- Customer comparison (AC7, AC8) ------------------------------------------------------

it('shows the customer the quotes side by side with the pro\'s details (AC7)', function (): void {
    $first = screenQuotingPro('Dlamini Plumbing');
    $second = screenQuotingPro('Naidoo Plumbing');
    $job = screenQuoteJob();
    screenSubmit($job, $first, deposit: 20);
    screenSubmit($job, $second);
    $this->actingAs($job->customer);

    Livewire::test(CustomerJob::class, ['job' => $job])
        ->assertSee('Estimates (2 of 5)')->assertSee('Dlamini Plumbing')->assertSee('Naidoo Plumbing')
        ->assertSee('R 570.50')->assertSee('R 114.10')->assertSee('Can come tomorrow morning.')
        ->assertSee('Labour')->assertSee('Materials')->assertSee('On GetSorted since');
});

it('shows "waiting for quotes" before any quote arrives (AC7, UX)', function (): void {
    screenQuotingPro();
    $job = screenQuoteJob();
    $this->actingAs($job->customer);

    Livewire::test(CustomerJob::class, ['job' => $job])->assertSee('Waiting for quotes')->assertDontSee('Accept this estimate');
});

it('never offers withdrawn, superseded or out-of-date quotes to the customer (AC7, AC12)', function (): void {
    $withdrawn = screenQuotingPro('Withdrawn Co');
    $revised = screenQuotingPro('Revised Co');
    $job = screenQuoteJob();
    app(WithdrawQuote::class)->handle($withdrawn->user, screenSubmit($job, $withdrawn), 'Busy');
    $old = screenSubmit($job, $revised);
    app(ReviseQuote::class)->handle($revised->user, $old, new QuoteDraft(
        [new QuoteLineData(LineKind::Labour, 'New price', '1', 99900)], 0, CarbonImmutable::now('Africa/Johannesburg')->addDay()->startOfDay(), 1, null,
    ));
    $this->actingAs($job->customer);

    Livewire::test(CustomerJob::class, ['job' => $job])
        ->assertDontSee('Withdrawn Co')->assertDontSee('R 570.50')->assertSee('R 999.00')->assertSee('Accept this estimate');

    // Past its valid-until date but before the scheduler runs: shown as expired, not acceptable.
    $this->travel(2)->days();
    Livewire::test(CustomerJob::class, ['job' => $job])
        ->assertSee('This estimate has expired')->assertDontSee('Accept this estimate');
});

it('hides a pro\'s profile photo that admins have not verified (AC7, privacy)', function (): void {
    $pro = screenQuotingPro();
    $document = $pro->documents()->forceCreate(['type' => DocumentType::ProfilePhoto, 'status' => 'flagged']);
    $document->addMedia(UploadedFile::fake()->image('me.jpg', 20, 20))->toMediaCollection('file', 'media');
    $job = screenQuoteJob();
    $quote = screenSubmit($job, $pro);

    $this->actingAs($job->customer)->get($quote->proPhotoUrl())->assertNotFound();
    expect($quote->fresh()->hasProPhoto())->toBeFalse();
});

it('shows a quoting pro\'s profile photo to the customer through a signed link only (AC7)', function (): void {
    $pro = screenQuotingPro();
    $document = $pro->documents()->forceCreate(['type' => DocumentType::ProfilePhoto, 'status' => 'verified']);
    $document->addMedia(UploadedFile::fake()->image('me.jpg', 20, 20))->toMediaCollection('file', 'media');
    $job = screenQuoteJob();
    $quote = screenSubmit($job, $pro);
    $url = $quote->proPhotoUrl();

    $this->actingAs($job->customer)->get($url)->assertOk();
    $this->actingAs(User::factory()->customer()->create())->get($url)->assertNotFound();
});

it('accepts a quote after confirmation and then shows the pro\'s contact details (AC8, AC9)', function (): void {
    $pro = screenQuotingPro();
    $job = screenQuoteJob();
    $quote = screenSubmit($job, $pro);
    $this->actingAs($job->customer);

    Livewire::test(CustomerJob::class, ['job' => $job])
        ->call('confirmAccept', $quote->public_id)->assertSee('Accept this estimate for R 570.50?')
        ->call('accept')->assertHasNoErrors()
        ->assertSee('Booked with Dlamini Plumbing')->assertSee($pro->user->phone_e164)->assertSee('Keep payments on GetSorted');

    expect($job->fresh()->status)->toBe(ServiceJobStatus::Scheduled);
});

it('shows "payment opens soon" when the accepted quote has a deposit (AC8, decision 1)', function (): void {
    $pro = screenQuotingPro();
    $job = screenQuoteJob();
    $quote = screenSubmit($job, $pro, deposit: 20);
    app(AcceptQuote::class)->handle($job->customer, $quote);
    $this->actingAs($job->customer);

    Livewire::test(CustomerJob::class, ['job' => $job])->assertSee('Payment opens soon')->assertSee('R 114.10');
});

// --- Contact details (AC9) -----------------------------------------------------------------

it('reveals the customer\'s contact details and address only to the accepted pro (AC9)', function (): void {
    $winner = screenQuotingPro('Winner');
    $loser = screenQuotingPro('Loser');
    $job = screenQuoteJob();
    $chosen = screenSubmit($job, $winner);
    screenSubmit($job, $loser);

    $this->actingAs($winner->user);
    Livewire::test(ProJob::class, ['invite' => screenInvite($job, $winner)])
        ->assertDontSee('7 Private Lane')->assertDontSee('829990000')->assertDontSee('Nomvula');

    app(AcceptQuote::class)->handle($job->customer, $chosen);

    Livewire::test(ProJob::class, ['invite' => screenInvite($job, $winner)])
        ->assertSee('Nomvula')->assertSee('+27829990000')->assertSee('7 Private Lane')->assertSee('Home')
        ->assertSee('Keep payments on GetSorted')->assertDontSee('Secretname');

    $this->actingAs($loser->user);
    Livewire::test(ProJob::class, ['invite' => screenInvite($job, $loser)])
        ->assertDontSee('7 Private Lane')->assertDontSee('829990000')->assertDontSee('Nomvula');

    // The contact policy, both ways.
    expect($winner->user->can('viewContact', $job->fresh()))->toBeTrue()
        ->and($loser->user->can('viewContact', $job->fresh()))->toBeFalse()
        ->and($job->customer->can('viewContact', $job->fresh()))->toBeFalse()
        ->and($job->fresh()->events()->pluck('event_type')->all())->toContain('quote_accepted');
});

// --- Admin (AC13) ------------------------------------------------------------------------

it('shows admins the job\'s quotes, read-only, with the masking flag (AC13)', function (): void {
    $pro = screenQuotingPro();
    $pro->forceFill(['contact_masking_count' => 3])->save();
    $job = screenQuoteJob();
    $quote = screenSubmit($job, $pro);
    $admin = User::factory()->create();
    $admin->assignRole(Role::AdminSupport->value);
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');

    Livewire::test(QuotesRelationManager::class, ['ownerRecord' => $job, 'pageClass' => ViewServiceJob::class])
        ->assertCanSeeTableRecords([$quote])->assertSee('Dlamini Plumbing')->assertSee('R 570.50')->assertSee('Flagged')
        ->assertTableActionDoesNotExist('edit')->assertTableActionDoesNotExist('delete');
});

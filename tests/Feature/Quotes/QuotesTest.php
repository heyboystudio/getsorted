<?php

declare(strict_types=1);

use App\Domain\Matching\Actions\RunMatchingSchedule;
use App\Domain\Matching\Enums\InviteStatus;
use App\Domain\Quotes\Actions\AcceptQuote;
use App\Domain\Quotes\Actions\ExpireQuotesAndJobs;
use App\Domain\Quotes\Actions\ReviseQuote;
use App\Domain\Quotes\Actions\SubmitQuote;
use App\Domain\Quotes\Actions\WithdrawQuote;
use App\Domain\Quotes\Data\QuoteDraft;
use App\Domain\Quotes\Data\QuoteLineData;
use App\Domain\Quotes\Enums\LineKind;
use App\Domain\Quotes\Enums\QuoteStatus;
use App\Domain\Quotes\Exceptions\CannotQuote;
use App\Domain\Quotes\Support\ContactMasker;
use App\Domain\Quotes\Support\QuoteCalculator;
use App\Domain\ServiceJobs\Actions\PostServiceJob;
use App\Domain\ServiceJobs\Actions\SaveBookingDraft;
use App\Domain\ServiceJobs\Data\BookingData;
use App\Domain\ServiceJobs\Enums\ServiceJobStatus;
use App\Domain\ServiceJobs\Enums\TimeWindow;
use App\Models\Pro;
use App\Models\Property;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use App\Models\User;
use App\Settings\MatchingSettings;
use App\Settings\QuoteSettings;
use App\Support\Rand;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CatalogueSeeder::class);
    $settings = app(MatchingSettings::class);
    $settings->invite_count = 6;
    $settings->save();
});

function quotingPros(int $count, ?string $vat = null): array
{
    return collect(range(1, $count))->map(function () use ($vat): Pro {
        $pro = proNear(['plumbing'], 2);
        $pro->forceFill(['vat_number' => $vat])->save();

        return $pro;
    })->all();
}

function postedJob(): ServiceJob
{
    $customer = User::factory()->customer()->create();
    $property = Property::factory()->for($customer)->create();
    $draft = app(SaveBookingDraft::class)->handle($customer, tradeOf('plumbing'), null, new BookingData(
        facts: [['id' => 'f1', 'text' => 'tap drips when fully closed', 'turn' => 1]], notes: 'Under the sink.', propertyPublicId: $property->public_id,
        preferredDate: now()->toImmutable()->addDays(2), timeWindow: TimeWindow::Morning,
    ));

    return app(PostServiceJob::class)->handle($customer, $draft);
}

/** A typical quote: R450 labour (1.5 h × R300), R120.50 materials, R150 call-out. */
function draftQuote(int $depositPercent = 20, ?string $notes = null, int $validityDays = 7): QuoteDraft
{
    return new QuoteDraft(
        lines: [
            new QuoteLineData(LineKind::Labour, 'Replace tap washer', '1.5', 30000),
            new QuoteLineData(LineKind::Materials, 'Washer kit', '1', 12050),
            new QuoteLineData(LineKind::Callout, 'Call-out', '1', 15000),
        ],
        depositPercent: $depositPercent,
        earliestStartDate: CarbonImmutable::now('Africa/Johannesburg')->addDay()->startOfDay(),
        validityDays: $validityDays,
        notes: $notes,
    );
}

function inviteFor(ServiceJob $job, Pro $pro): ServiceJobInvite
{
    $invite = ServiceJobInvite::query()->where('service_job_id', $job->id)->where('pro_id', $pro->id)->with('pro.user')->sole();

    // Pros accept the job before they can quote; most tests here start from that point.
    if ($invite->status->isOpen()) {
        $invite->forceFill(['status' => 'accepted'])->save();
    }

    return $invite->refresh();
}

function submitFor(ServiceJob $job, Pro $pro, ?QuoteDraft $draft = null): Quote
{
    return app(SubmitQuote::class)->handle($pro->user, inviteFor($job, $pro), $draft ?? draftQuote());
}

// --- Money (AC2) ---------------------------------------------------------------------

it('calculates every total in cents from the lines, without VAT for pros without a VAT number (AC2)', function (): void {
    $totals = app(QuoteCalculator::class)->calculate(draftQuote(depositPercent: 20), vatRegistered: false);

    expect($totals->lineTotalsCents)->toBe([45000, 12050, 15000])
        ->and($totals->labourCents)->toBe(45000)->and($totals->materialsCents)->toBe(12050)->and($totals->calloutCents)->toBe(15000)
        ->and($totals->vatCents)->toBe(0)->and($totals->totalCents)->toBe(72050)
        ->and($totals->depositCents)->toBe(14410)
        // 12% of labour + call-out (R600) = R72; nothing on materials (decision 3).
        ->and($totals->commissionEstimateCents)->toBe(7200)->and($totals->payoutEstimateCents)->toBe(64850);
});

it('adds 15% VAT for pros with a VAT number and rounds to the cent (AC2, decision 2)', function (): void {
    $draft = new QuoteDraft([new QuoteLineData(LineKind::Labour, 'Odd amount', '0.33', 1001)], 33, CarbonImmutable::now('Africa/Johannesburg')->addDay(), 7, null);

    $totals = app(QuoteCalculator::class)->calculate($draft, vatRegistered: true);

    // 0.33 × R10.01 = R3.3033 → R3.30; VAT R0.495 → R0.50 (half up); total R3.80; deposit 33% = R1.254 → R1.25.
    expect($totals->lineTotalsCents)->toBe([330])->and($totals->vatCents)->toBe(50)
        ->and($totals->totalCents)->toBe(380)->and($totals->depositCents)->toBe(125);
});

it('refuses quotes that break the rules (AC1, rules)', function (QuoteDraft $draft, string $field): void {
    [$pro] = quotingPros(1);
    $job = postedJob();

    try {
        submitFor($job, $pro, $draft);
        $this->fail('Expected the quote to be refused.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }

    expect(Quote::query()->count())->toBe(0);
})->with([
    'no lines' => [new QuoteDraft([], 0, CarbonImmutable::now('Africa/Johannesburg')->addDay(), 7, null), 'lines'],
    'two call-outs' => [new QuoteDraft([new QuoteLineData(LineKind::Callout, 'A', '1', 100), new QuoteLineData(LineKind::Callout, 'B', '1', 100)], 0, CarbonImmutable::now('Africa/Johannesburg')->addDay(), 7, null), 'lines'],
    'negative price' => [new QuoteDraft([new QuoteLineData(LineKind::Labour, 'A', '1', -100)], 0, CarbonImmutable::now('Africa/Johannesburg')->addDay(), 7, null), 'lines.0.unit_price'],
    'zero quantity' => [new QuoteDraft([new QuoteLineData(LineKind::Labour, 'A', '0', 100)], 0, CarbonImmutable::now('Africa/Johannesburg')->addDay(), 7, null), 'lines.0.quantity'],
    'long description' => [new QuoteDraft([new QuoteLineData(LineKind::Labour, str_repeat('a', 121), '1', 100)], 0, CarbonImmutable::now('Africa/Johannesburg')->addDay(), 7, null), 'lines.0.description'],
    'deposit over the cap' => [draftQuote(depositPercent: 51), 'deposit_percent'],
    'start date in the past' => [new QuoteDraft([new QuoteLineData(LineKind::Labour, 'A', '1', 100)], 0, CarbonImmutable::now('Africa/Johannesburg')->subDays(2), 7, null), 'earliest_start_date'],
    'start date too far' => [new QuoteDraft([new QuoteLineData(LineKind::Labour, 'A', '1', 100)], 0, CarbonImmutable::now('Africa/Johannesburg')->addDays(61), 7, null), 'earliest_start_date'],
    'validity too long' => [draftQuote(validityDays: 31), 'validity_days'],
    'total over R500 000' => [new QuoteDraft([new QuoteLineData(LineKind::Labour, 'Huge', '1', 50_000_001)], 0, CarbonImmutable::now('Africa/Johannesburg')->addDay(), 7, null), 'total'],
]);

it('uses the deposit cap from settings (AC1)', function (): void {
    $settings = app(QuoteSettings::class);
    $settings->max_deposit_percent = 10;
    $settings->save();
    [$pro] = quotingPros(1);

    expect(fn () => submitFor(postedJob(), $pro, draftQuote(depositPercent: 20)))->toThrow(ValidationException::class);
});

// --- Submitting (AC3, AC4, AC6) --------------------------------------------------------

it('submits a quote: version 1, invite quoted, customer told, count up (AC3)', function (): void {
    [$pro] = quotingPros(1);
    $job = postedJob();

    $quote = submitFor($job, $pro);

    expect($quote->status)->toBe(QuoteStatus::Submitted)->and($quote->version)->toBe(1)
        ->and($quote->total_cents)->toBe(72050)->and($quote->lines)->toHaveCount(3)
        ->and($quote->valid_until->toDateString())->toBe(now('Africa/Johannesburg')->addDays(7)->toDateString())
        ->and(inviteFor($job, $pro)->status)->toBe(InviteStatus::Quoted)
        ->and($job->fresh()->quotes_count)->toBe(1);
    expect(noticeCount($job->customer, 'quote_received'))->toBe(1);

    expect(fn () => submitFor($job, $pro))->toThrow(CannotQuote::class);
    expect(Quote::query()->count())->toBe(1);
});

it('accepts the first five quotes, then closes the other invites as full (spec 020)', function (): void {
    $settings = app(MatchingSettings::class);
    $settings->invite_count = 7;
    $settings->save();
    $pros = quotingPros(7);
    $job = postedJob();

    foreach (array_slice($pros, 0, 5) as $pro) {
        submitFor($job, $pro);
    }

    expect($job->fresh()->quotes_count)->toBe(5)
        ->and(inviteFor($job, $pros[5])->status)->toBe(InviteStatus::Closed)
        ->and(fn () => submitFor($job, $pros[5]))->toThrow(CannotQuote::class, 'This job is full');
    expect(Quote::query()->count())->toBe(5);
});

it('uses the quote cap from settings', function (): void {
    $settings = app(MatchingSettings::class);
    $settings->max_quotes = 2;
    $settings->save();
    $pros = quotingPros(3);
    $job = postedJob();

    submitFor($job, $pros[0]);
    submitFor($job, $pros[1]);

    expect(fn () => submitFor($job, $pros[2]))->toThrow(CannotQuote::class, 'This job is full');
});

it('only lets the invited pro quote, on an open invite (AC1, security)', function (): void {
    [$pro, $other] = quotingPros(2);
    $job = postedJob();
    $invite = inviteFor($job, $pro);

    expect(fn () => app(SubmitQuote::class)->handle($other->user, $invite, draftQuote()))->toThrow(AuthorizationException::class);

    $invite->forceFill(['status' => InviteStatus::Declined])->save();
    expect(fn () => app(SubmitQuote::class)->handle($pro->user, $invite->fresh(), draftQuote()))->toThrow(CannotQuote::class);
});

it('masks contact and bank details in quotes and flags repeat offenders (AC6)', function (): void {
    [$pro] = quotingPros(1);
    $job = postedJob();
    $draft = new QuoteDraft(
        [new QuoteLineData(LineKind::Labour, 'Call 082 123 4567', '1', 10000)],
        0, CarbonImmutable::now('Africa/Johannesburg')->addDay(), 7, 'Pay cash to acc no 1234567890 or mail me@example.com',
    );

    $quote = submitFor($job, $pro, $draft);

    expect($quote->lines->first()->description)->toBe('Call [phone]')
        ->and($quote->notes)->not->toContain('1234567890')->not->toContain('me@example.com')
        ->and($pro->fresh()->contact_masking_count)->toBe(1);

    app(ReviseQuote::class)->handle($pro->user, $quote, $draft);
    app(ReviseQuote::class)->handle($pro->user, $quote->fresh()->latestVersion(), $draft);

    expect($pro->fresh()->contact_masking_count)->toBe(3)->and($pro->fresh()->isMaskingFlagged())->toBeTrue();
});

it('leaves ordinary trade wording alone and does not flag it (AC6)', function (string $text): void {
    expect(ContactMasker::mask($text))->toBe([$text, false]);
})->with(['Tile 12 m²', '½ day labour', 'Branch line 22 - 22 - 15', 'Bank of the river: 3 m pipe', 'Fix 2 taps on 12/10/2026']);

it('still masks bank account and branch numbers (AC6)', function (string $text): void {
    [$masked, $changed] = ContactMasker::mask($text);

    expect($changed)->toBeTrue()->and($masked)->toContain('[bank details]');
})->with(['Acc no 62 1234 5678', 'Branch code 250655', 'Pay into account 1234-5678-90']);

it('reads rand amounts the way South Africans type them (AC1)', function (string $input, ?int $cents): void {
    expect(Rand::toCents($input))->toBe($cents);
})->with([
    ['350', 35000], ['120.50', 12050], ['120,50', 12050], ['120,5', 12050], ['R 1 234,50', 123450],
    ['1 234.50', 123450], ['1,234.50', 123450], ['1,234', 123400], ['12,3456', null], ['1,23,4', null], ['abc', null], ['-5', null],
]);

it('refuses a quantity above 9 999 (AC1)', function (): void {
    [$pro] = quotingPros(1);

    try {
        submitFor(postedJob(), $pro, new QuoteDraft([new QuoteLineData(LineKind::Labour, 'Lots', '9999.99', 100)], 0, CarbonImmutable::now('Africa/Johannesburg')->addDay(), 7, null));
        $this->fail('Expected a validation error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('lines.0.quantity');
    }
});

it('limits how often a pro can send, revise or withdraw quotes (security)', function (): void {
    config()->set('getsorted.quotes.changes_per_hour', 2);
    [$pro] = quotingPros(1);
    $job = postedJob();
    $quote = submitFor($job, $pro);
    app(ReviseQuote::class)->handle($pro->user, $quote, draftQuote());

    try {
        app(WithdrawQuote::class)->handle($pro->user, $quote->fresh()->latestVersion(), 'Busy');
        $this->fail('Expected the rate limit.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('quote');
    }
    expect(Quote::query()->where('status', QuoteStatus::Submitted)->count())->toBe(1);
});

// --- Revising and withdrawing (AC5) ------------------------------------------------------

it('revises a quote as a new version without using another slot (AC5)', function (): void {
    [$pro] = quotingPros(1);
    $job = postedJob();
    $first = submitFor($job, $pro);

    $second = app(ReviseQuote::class)->handle($pro->user, $first, draftQuote(depositPercent: 0));

    expect($first->fresh()->status)->toBe(QuoteStatus::Superseded)
        ->and($second->version)->toBe(2)->and($second->supersedes_quote_id)->toBe($first->id)
        ->and($second->deposit_cents)->toBe(0)
        ->and($job->fresh()->quotes_count)->toBe(1);
    expect(allNoticeCount('quote_revised'))->toBe(1);

    expect(fn () => app(ReviseQuote::class)->handle($pro->user, $first->fresh(), draftQuote()))->toThrow(CannotQuote::class);
});

it('withdraws a quote with a reason, freeing the slot (AC5)', function (): void {
    [$pro] = quotingPros(1);
    $job = postedJob();
    $quote = submitFor($job, $pro);

    expect(fn () => app(WithdrawQuote::class)->handle($pro->user, $quote, ' '))->toThrow(ValidationException::class);
    app(WithdrawQuote::class)->handle($pro->user, $quote, 'Double-booked that week.');

    expect($quote->fresh()->status)->toBe(QuoteStatus::Withdrawn)
        ->and($quote->fresh()->withdraw_reason)->toBe('Double-booked that week.')
        ->and($job->fresh()->quotes_count)->toBe(0);
    expect(allNoticeCount('quote_withdrawn'))->toBe(1);
    $entry = Activity::query()->where('description', 'quote_withdrawn')->sole();
    expect($entry->causer_id)->toBe($pro->user_id)->and($entry->subject_id)->toBe($job->id)
        ->and($entry->properties['reason'])->toBe('Double-booked that week.');
});

it('lets only the quoting pro revise or withdraw (AC5, security)', function (): void {
    [$pro, $other] = quotingPros(2);
    $quote = submitFor(postedJob(), $pro);

    expect(fn () => app(ReviseQuote::class)->handle($other->user, $quote, draftQuote()))->toThrow(AuthorizationException::class)
        ->and(fn () => app(WithdrawQuote::class)->handle($other->user, $quote, 'x'))->toThrow(AuthorizationException::class);
});

// --- Accepting (AC8–AC10) ----------------------------------------------------------------

it('accepts a quote without a deposit: job scheduled, others declined, invites closed, allocation written (AC8)', function (): void {
    [$winner, $loser, $waiting] = quotingPros(3);
    $job = postedJob();
    $chosen = submitFor($job, $winner, draftQuote(depositPercent: 0));
    $other = submitFor($job, $loser);

    app(AcceptQuote::class)->handle($job->customer, $chosen);

    $job->refresh();
    expect($job->status)->toBe(ServiceJobStatus::Scheduled)
        ->and($job->accepted_quote_id)->toBe($chosen->id)
        ->and($job->scheduled_for->toDateString())->toBe($chosen->earliest_start_date->toDateString())
        ->and($chosen->fresh()->status)->toBe(QuoteStatus::Accepted)->and($chosen->fresh()->accepted_at)->not->toBeNull()
        ->and($other->fresh()->status)->toBe(QuoteStatus::Declined)
        ->and(inviteFor($job, $waiting)->status)->toBe(InviteStatus::Closed)
        ->and(DB::table('pro_job_allocations')->where('pro_id', $winner->id)->where('service_job_id', $job->id)->count())->toBe(1)
        ->and($job->events()->pluck('event_type')->last())->toBe('quote_accepted');

    expect(noticeCount($winner->user, 'quote_accepted'))->toBe(1);
    expect(noticeCount($loser->user, 'quote_not_chosen'))->toBe(1);
});

it('moves the job to awaiting deposit when the accepted quote has a deposit (AC8, decision 1)', function (): void {
    [$pro] = quotingPros(1);
    $job = postedJob();

    app(AcceptQuote::class)->handle($job->customer, submitFor($job, $pro, draftQuote(depositPercent: 20)));

    expect($job->fresh()->status)->toBe(ServiceJobStatus::AwaitingDeposit);
});

it('refuses to accept the wrong quote, twice, or for someone else (AC10)', function (string $case): void {
    [$pro, $second] = quotingPros(2);
    $job = postedJob();
    $quote = submitFor($job, $pro);
    $customer = $job->customer;

    $attempt = match ($case) {
        'another customer' => fn () => app(AcceptQuote::class)->handle(User::factory()->customer()->create(), $quote),
        'withdrawn' => function () use ($pro, $quote, $customer): void {
            app(WithdrawQuote::class)->handle($pro->user, $quote, 'Busy');
            app(AcceptQuote::class)->handle($customer, $quote->fresh());
        },
        'superseded' => function () use ($pro, $quote, $customer): void {
            app(ReviseQuote::class)->handle($pro->user, $quote, draftQuote());
            app(AcceptQuote::class)->handle($customer, $quote->fresh());
        },
        'past its validity' => function () use ($quote, $customer): void {
            $this->travel(8)->days();
            app(AcceptQuote::class)->handle($customer, $quote->fresh());
        },
        'twice' => function () use ($second, $job, $quote, $customer): void {
            $other = submitFor($job, $second);
            app(AcceptQuote::class)->handle($customer, $quote);
            app(AcceptQuote::class)->handle($customer, $other);
        },
        'suspended pro' => function () use ($pro, $quote, $customer): void {
            $pro->forceFill(['status' => 'suspended'])->save();
            app(AcceptQuote::class)->handle($customer, $quote->fresh());
        },
    };

    try {
        $attempt();
        $this->fail('Expected the acceptance to be refused.');
    } catch (CannotQuote|AuthorizationException) {
        // Refused, as expected.
    }
    // Nothing else changed: the job stays open (or keeps only the first acceptance) and no extra allocation is written.
    expect(Quote::query()->where('status', QuoteStatus::Accepted)->count())->toBe($case === 'twice' ? 1 : 0)
        ->and($job->fresh()->status)->toBe($case === 'twice' ? ServiceJobStatus::AwaitingDeposit : ServiceJobStatus::Open)
        ->and(DB::table('pro_job_allocations')->count())->toBe($case === 'twice' ? 1 : 0)
        ->and($job->events()->where('event_type', 'quote_accepted')->count())->toBe($case === 'twice' ? 1 : 0);

    if ($case !== 'twice') {
        expect($job->invites()->where('status', InviteStatus::Closed)->count())->toBe($case === 'withdrawn' ? 1 : 0);
    }
})->with(['another customer', 'withdrawn', 'superseded', 'past its validity', 'twice', 'suspended pro']);

// --- Timers (AC11, AC12) -------------------------------------------------------------

it('expires a job with no accepted quote after the quote window, safely twice (AC11)', function (): void {
    [$pro, $waiting] = quotingPros(2);
    $job = postedJob();
    $quote = submitFor($job, $pro);

    $this->travel(73)->hours();
    app(ExpireQuotesAndJobs::class)->handle();
    app(ExpireQuotesAndJobs::class)->handle();

    expect($job->fresh()->status)->toBe(ServiceJobStatus::Expired)
        ->and($quote->fresh()->status)->toBe(QuoteStatus::Expired)
        ->and(inviteFor($job, $waiting)->status)->toBe(InviteStatus::Closed)
        ->and($job->events()->where('event_type', 'job_expired')->count())->toBe(1)
        ->and(Activity::query()->where('description', 'job_expired')->where('subject_id', $job->id)->count())->toBe(1);
    expect(allNoticeCount('job_expired'))->toBe(1);
});

it('expires a quote after its validity, then lets the pro send a fresh version (AC12)', function (): void {
    [$pro] = quotingPros(1);
    $job = postedJob();
    $quote = submitFor($job, $pro, draftQuote(validityDays: 1));

    $this->travel(2)->days();
    app(ExpireQuotesAndJobs::class)->handle();

    expect($quote->fresh()->status)->toBe(QuoteStatus::Expired)->and($job->fresh()->quotes_count)->toBe(0);

    $fresh = app(ReviseQuote::class)->handle($pro->user, $quote->fresh(), draftQuote());
    expect($fresh->status)->toBe(QuoteStatus::Submitted)->and($fresh->version)->toBe(2)->and($job->fresh()->quotes_count)->toBe(1);
});

it('stops inviting once the job is full (spec 009 AC3, spec 020)', function (): void {
    $settings = app(MatchingSettings::class);
    $settings->invite_count = 4;
    $settings->max_quotes = 2;
    $settings->save();
    quotingPros(6);
    $job = postedJob();
    $invited = $job->invites()->limit(2)->pluck('pro_id')->all();
    foreach (Pro::query()->whereIn('id', $invited)->with('user')->get() as $pro) {
        submitFor($job, $pro);
    }

    $this->travel(31)->minutes();
    app(RunMatchingSchedule::class)->handle();

    expect($job->invites()->count())->toBe(4);
});

it('schedules quote and job expiry every five minutes (rules)', function (): void {
    $events = collect(app(Schedule::class)->events());
    $expiry = $events->first(fn ($event): bool => str_contains((string) $event->command, 'getsorted:expire-quotes'));

    expect($expiry)->not->toBeNull()->and($expiry->expression)->toBe('*/5 * * * *');
});

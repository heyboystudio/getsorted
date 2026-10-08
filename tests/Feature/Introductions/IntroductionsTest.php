<?php

declare(strict_types=1);

use App\Contracts\Data\PaymentEvent;
use App\Contracts\Data\PaymentEventType;
use App\Domain\Accounts\Enums\Role;
use App\Domain\Introductions\Actions\ApplyPaymentEvent;
use App\Domain\Introductions\Actions\StartCreditPurchase;
use App\Domain\Introductions\Enums\CreditPurchaseStatus;
use App\Domain\Introductions\Enums\IntroductionKind;
use App\Domain\Introductions\Exceptions\CannotBuyCredit;
use App\Domain\Introductions\Support\ProCredit;
use App\Domain\Introductions\Support\ProIdentity;
use App\Domain\Quotes\Actions\AcceptQuote;
use App\Domain\Quotes\Data\QuoteDraft;
use App\Domain\Quotes\Data\QuoteLineData;
use App\Domain\Quotes\Enums\LineKind;
use App\Domain\Quotes\Support\QuoteCalculator;
use App\Domain\Quotes\Support\QuoteRules;
use App\Filament\Admin\Pages\IntroductionSettingsPage;
use App\Livewire\Pros\Credit;
use App\Livewire\Pros\Jobs\Show;
use App\Livewire\Pros\Welcome;
use App\Models\CreditPurchase;
use App\Models\Introduction;
use App\Models\Pro;
use App\Models\ProCreditEntry;
use App\Models\Quote;
use App\Models\ServiceJob;
use App\Models\ServiceJobInvite;
use App\Models\User;
use App\Settings\IntroductionSettings;
use Brick\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** Spec 023: the introduction record, the pro's introduction fee and prepaid credit. */
uses(RefreshDatabase::class);

function introSettings(bool $enabled, int $free = 10): void
{
    app(IntroductionSettings::class)->fill(['fee_enabled' => $enabled, 'fee_cents' => 9900, 'free_introductions' => $free])->save();
}

function quoteFor(?Pro $pro = null): Quote
{
    $job = ServiceJob::factory()->open()->create();

    return Quote::factory()->create(['service_job_id' => $job->id, 'pro_id' => ($pro ?? Pro::factory()->approved()->create())->id]);
}

function creditPro(int $cents): Pro
{
    $pro = Pro::factory()->approved()->create();

    if ($cents !== 0) {
        (new ProCreditEntry)->forceFill(['pro_id' => $pro->id, 'type' => 'purchase', 'amount_cents' => $cents, 'idempotency_key' => 'seed:'.$pro->id, 'note' => 'Test'])->save();
    }

    return $pro;
}

// --- The introduction record (AC5–AC7) -------------------------------------------------------

it('records a free introduction, with no charge, when the fee is switched off', function (): void {
    introSettings(false, 0);
    $quote = quoteFor();

    app(AcceptQuote::class)->handle($quote->serviceJob->customer, $quote);

    $introduction = Introduction::query()->sole();
    expect($introduction->kind)->toBe(IntroductionKind::Free)->and($introduction->fee_cents)->toBe(0)
        ->and($introduction->service_job_id)->toBe($quote->service_job_id)->and($introduction->quote_id)->toBe($quote->id)
        ->and($introduction->pro_id)->toBe($quote->pro_id)->and($introduction->customer_id)->toBe($quote->serviceJob->customer_id)
        ->and(ProCreditEntry::query()->count())->toBe(0);
});

it('keeps a new pro\'s first introductions free once the fee is on', function (): void {
    introSettings(true, 10);
    $quote = quoteFor();

    app(AcceptQuote::class)->handle($quote->serviceJob->customer, $quote);

    expect(Introduction::query()->sole()->kind)->toBe(IntroductionKind::Free)->and(ProCreditEntry::query()->count())->toBe(0)
        ->and(app(ProCredit::class)->freeLeft($quote->pro))->toBe(9);
});

it('takes the fee from the pro\'s credit once the free allowance is used', function (): void {
    introSettings(true, 0);
    $pro = creditPro(29_700);
    $quote = quoteFor($pro);

    app(AcceptQuote::class)->handle($quote->serviceJob->customer, $quote);

    $introduction = Introduction::query()->sole();
    expect($introduction->kind)->toBe(IntroductionKind::Credit)->and($introduction->fee_cents)->toBe(9_900)
        ->and(ProCreditEntry::query()->where('introduction_id', $introduction->id)->sole()->amount_cents)->toBe(-9_900)
        ->and(app(ProCredit::class)->balanceCents($pro))->toBe(19_800);
});

it('lets a pro send estimates only while their credit covers an introduction', function (): void {
    introSettings(true, 0);
    $broke = creditPro(5_000);
    $funded = creditPro(9_900);

    expect(app(ProCredit::class)->canQuote($broke))->toBeFalse()->and(app(ProCredit::class)->canQuote($funded))->toBeTrue();

    introSettings(false, 0);
    expect(app(ProCredit::class)->canQuote($broke))->toBeTrue();
});

it('never edits or removes ledger rows and introductions', function (): void {
    introSettings(false, 0);
    $quote = quoteFor();
    app(AcceptQuote::class)->handle($quote->serviceJob->customer, $quote);
    $entry = creditPro(100)->id;

    expect(fn () => Introduction::query()->sole()->forceFill(['fee_cents' => 1])->save())->toThrow(LogicException::class)
        ->and(fn () => ProCreditEntry::query()->where('pro_id', $entry)->sole()->delete())->toThrow(LogicException::class);
});

// --- Naming before the introduction (AC1) ------------------------------------------------------

it('shows a pro\'s first name until the client chooses them, then the business name', function (): void {
    $quote = quoteFor();
    $pro = $quote->pro;
    $pro->forceFill(['business_name' => 'Dlamini Plumbing'])->save();

    expect(ProIdentity::displayName($pro, $quote->serviceJob))->toBe($pro->user->first_name);

    introSettings(false, 0);
    app(AcceptQuote::class)->handle($quote->serviceJob->customer, $quote);

    expect(ProIdentity::displayName($pro->refresh(), $quote->serviceJob->refresh()))->toBe('Dlamini Plumbing');
});

// --- Buying credit (AC9–AC10) -----------------------------------------------------------------

it('records a pending purchase and sends the pro to pay', function (): void {
    introSettings(true, 0);
    $pro = Pro::factory()->approved()->create();

    $url = app(StartCreditPurchase::class)->handle($pro->user, $pro, 29_700);

    $purchase = CreditPurchase::query()->sole();
    expect($url)->toStartWith('https://payments.fake.test/checkout/')
        ->and($purchase->status)->toBe(CreditPurchaseStatus::Pending)->and($purchase->amount_cents)->toBe(29_700)->and($purchase->pro_id)->toBe($pro->id)
        ->and(ProCreditEntry::query()->count())->toBe(0);
});

it('refuses unknown packs, another pro\'s account, and credit while introductions are free', function (): void {
    introSettings(true, 0);
    $pro = Pro::factory()->approved()->create();
    $other = Pro::factory()->approved()->create();

    expect(fn () => app(StartCreditPurchase::class)->handle($pro->user, $pro, 12_345))->toThrow(CannotBuyCredit::class)
        ->and(fn () => app(StartCreditPurchase::class)->handle($other->user, $pro, 29_700))->toThrow(CannotBuyCredit::class);

    introSettings(false, 0);
    expect(fn () => app(StartCreditPurchase::class)->handle($pro->user, $pro, 29_700))->toThrow(CannotBuyCredit::class);
    expect(CreditPurchase::query()->count())->toBe(0);
});

function pendingPurchase(int $cents = 29_700): CreditPurchase
{
    $purchase = new CreditPurchase;
    $purchase->forceFill(['pro_id' => Pro::factory()->approved()->create()->id, 'amount_cents' => $cents, 'status' => CreditPurchaseStatus::Pending])->save();

    return $purchase;
}

function paymentEvent(CreditPurchase $purchase, PaymentEventType $type = PaymentEventType::PaymentSucceeded, ?int $cents = null): PaymentEvent
{
    return new PaymentEvent('pf_123', $type, $purchase->public_id, Money::ofMinor($cents ?? $purchase->amount_cents, 'ZAR'));
}

it('adds credit once from a successful payment, however often it is announced', function (): void {
    $purchase = pendingPurchase();

    app(ApplyPaymentEvent::class)->handle(paymentEvent($purchase));
    app(ApplyPaymentEvent::class)->handle(paymentEvent($purchase));

    expect(ProCreditEntry::query()->count())->toBe(1)->and(app(ProCredit::class)->balanceCents($purchase->pro))->toBe(29_700)
        ->and($purchase->refresh()->status)->toBe(CreditPurchaseStatus::Complete)->and($purchase->completed_at)->not->toBeNull();
});

it('adds nothing for a failed payment, a payment of the wrong amount, a pending one or an unknown reference', function (): void {
    $failed = pendingPurchase();
    app(ApplyPaymentEvent::class)->handle(paymentEvent($failed, PaymentEventType::PaymentFailed));

    $wrong = pendingPurchase();
    app(ApplyPaymentEvent::class)->handle(paymentEvent($wrong, cents: 100));

    $pending = pendingPurchase();
    app(ApplyPaymentEvent::class)->handle(paymentEvent($pending, PaymentEventType::PaymentPending));

    app(ApplyPaymentEvent::class)->handle(new PaymentEvent('pf_9', PaymentEventType::PaymentSucceeded, 'nope', Money::ofMinor(100, 'ZAR')));

    expect(ProCreditEntry::query()->count())->toBe(0)->and($failed->refresh()->status)->toBe(CreditPurchaseStatus::Failed)
        ->and($wrong->refresh()->status)->toBe(CreditPurchaseStatus::Pending)->and($pending->refresh()->status)->toBe(CreditPurchaseStatus::Pending);
});

// --- The pro's credit page (AC8, AC11) ---------------------------------------------------------

it('tells a pro introductions are free while the fee is off', function (): void {
    introSettings(false, 0);
    $pro = Pro::factory()->approved()->create();
    $this->actingAs($pro->user);

    Livewire::test(Credit::class)->assertSee('Introductions are free right now')->assertDontSee('Add credit');
});

it('shows balance, allowance and packs, and sends the pro to PayFast to buy one', function (): void {
    introSettings(true, 3);
    $pro = creditPro(19_800);
    $this->actingAs($pro->user);

    Livewire::test(Credit::class)->assertSee('R 198.00')->assertSee('3 free introductions left')->assertSee('R 297')->assertSee('R 990')
        ->call('buy', 29_700)->assertRedirectContains('https://payments.fake.test/checkout/');

    expect(CreditPurchase::query()->sole()->amount_cents)->toBe(29_700);
});

it('keeps the credit page away from pros who are not approved', function (): void {
    $pro = Pro::factory()->create();
    $this->actingAs($pro->user);

    Livewire::test(Credit::class)->assertRedirect(route('pros.status'));
});

// --- Estimate ranges (AC2–AC3) -------------------------------------------------------------

function rangeDraft(?int $high): QuoteDraft
{
    return new QuoteDraft([new QuoteLineData(LineKind::Labour, 'Fix tap', '1', 45_000)], 0, CarbonImmutable::now('Africa/Johannesburg')->addDay()->startOfDay(), 7, null, $high);
}

it('accepts a range whose top is above the total and refuses one that is not', function (): void {
    $totals = app(QuoteCalculator::class)->calculate(rangeDraft(null), false);

    app(QuoteRules::class)->check(rangeDraft(65_000), $totals);
    app(QuoteRules::class)->check(rangeDraft(null), $totals);

    foreach ([45_000, 10_000, 999_999_999] as $high) {
        expect(fn () => app(QuoteRules::class)->check(rangeDraft($high), $totals))->toThrow(ValidationException::class);
    }
});

// --- Admin settings (AC14) ---------------------------------------------------------------------

it('lets only super admins switch the fee on and change the amount, allowance and packs', function (): void {
    $support = User::factory()->create();
    $support->assignRole(Role::AdminSupport->value);
    $this->actingAs($support);
    Filament\Facades\Filament::setCurrentPanel('admin');
    expect(IntroductionSettingsPage::canAccess())->toBeFalse();

    $super = User::factory()->create();
    $super->assignRole(Role::AdminSuper->value);
    $this->actingAs($super);

    Livewire::test(IntroductionSettingsPage::class)
        ->fillForm(['fee_enabled' => true, 'fee_rand' => 120, 'free_introductions' => 5, 'packs_rand' => '240, 600'])
        ->call('save')->assertHasNoFormErrors();

    $settings = app(IntroductionSettings::class)->refresh();
    expect([$settings->fee_enabled, $settings->fee_cents, $settings->free_introductions, $settings->credit_pack_cents])->toBe([true, 12_000, 5, [24_000, 60_000]]);

    Livewire::test(IntroductionSettingsPage::class)
        ->fillForm(['fee_rand' => 0, 'free_introductions' => -1])
        ->call('save')->assertHasFormErrors(['fee_rand', 'free_introductions']);
});

// --- Topping up from where the pro is stuck ---------------------------------------------------------

it('opens a top-up popup instead of an error when a pro has no credit to send an estimate', function (): void {
    introSettings(true, 0);
    $pro = Pro::factory()->approved()->create();
    $job = ServiceJob::factory()->open()->create();
    $invite = ServiceJobInvite::factory()->create(['service_job_id' => $job->id, 'pro_id' => $pro->id, 'status' => 'accepted']);
    $this->actingAs($pro->user);

    Livewire::test(Show::class, ['invite' => $invite])
        ->call('startQuote')->assertSet('topUp', true)->assertSet('building', false)
        ->assertSee('Add credit to send this estimate')->assertSee('R 297')->assertSee('R 990')
        ->call('buyCredit', 29_700)->assertRedirectContains('https://payments.fake.test/checkout/')
        ->call('closeTopUp')->assertSet('topUp', false);

    expect(CreditPurchase::query()->sole()->amount_cents)->toBe(29_700);
});

it('lets a pro with credit start an estimate as normal', function (): void {
    introSettings(true, 0);
    $pro = creditPro(9_900);
    $job = ServiceJob::factory()->open()->create();
    $invite = ServiceJobInvite::factory()->create(['service_job_id' => $job->id, 'pro_id' => $pro->id, 'status' => 'accepted']);
    $this->actingAs($pro->user);

    Livewire::test(Show::class, ['invite' => $invite])->call('startQuote')->assertSet('topUp', false)->assertSet('building', true);
});

it('shows the credit balance with an add button on the pro\'s Today screen only while the fee is on', function (): void {
    $pro = creditPro(5_000);
    $this->actingAs($pro->user);

    introSettings(false, 0);
    Livewire::test(Welcome::class)->assertDontSee('Add credit');

    introSettings(true, 0);
    Livewire::test(Welcome::class)->assertSee('R 50.00')->assertSee('Add credit');
});

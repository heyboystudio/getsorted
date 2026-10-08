<?php

declare(strict_types=1);

use App\Contracts\Data\CheckoutRequest;
use App\Contracts\Data\PaymentEventType;
use App\Contracts\Exceptions\InvalidWebhookSignature;
use App\Contracts\PaymentGateway;
use App\Domain\Introductions\Enums\CreditPurchaseStatus;
use App\Domain\Introductions\Support\ProCredit;
use App\Integrations\PayFast\PayFastGateway;
use App\Models\CreditPurchase;
use App\Models\Pro;
use Brick\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

/** Spec 023: PayFast checkout links and instant transaction notifications. */
uses(RefreshDatabase::class);

const PF_MERCHANT = '10000100';
const PF_KEY = '46f0cd694581a';
const PF_PASSPHRASE = 'a-test-passphrase';

function payfast(?string $passphrase = PF_PASSPHRASE, bool $checkIp = false): PayFastGateway
{
    return new PayFastGateway(PF_MERCHANT, PF_KEY, $passphrase, true, 'https://example.test/webhooks/payfast', $checkIp);
}

/** A notification body signed the way PayFast signs it. */
function itn(array $overrides = [], string $passphrase = PF_PASSPHRASE): string
{
    $fields = array_merge([
        'm_payment_id' => 'REF1', 'pf_payment_id' => '1089250', 'payment_status' => 'COMPLETE', 'item_name' => 'GetSorted introduction credit',
        'amount_gross' => '297.00', 'amount_fee' => '-6.83', 'amount_net' => '290.17', 'merchant_id' => PF_MERCHANT,
    ], $overrides);

    $parts = [];
    foreach ($fields as $key => $value) {
        $parts[] = $key.'='.urlencode(trim((string) $value));
    }
    $parts[] = 'passphrase='.urlencode($passphrase);

    return http_build_query($fields).'&signature='.md5(implode('&', $parts));
}

it('builds a signed PayFast link for the amount and reference', function (): void {
    $checkout = payfast()->createCheckout(new CheckoutRequest(Money::ofMinor(29_700, 'ZAR'), 'REF1', 'GetSorted introduction credit', 'https://example.test/pros/credit', 'k1'));

    parse_str((string) parse_url($checkout->redirectUrl, PHP_URL_QUERY), $query);

    expect($checkout->redirectUrl)->toStartWith('https://sandbox.payfast.co.za/eng/process?')
        ->and($query['merchant_id'])->toBe(PF_MERCHANT)->and($query['amount'])->toBe('297.00')->and($query['m_payment_id'])->toBe('REF1')
        ->and($query['notify_url'])->toBe('https://example.test/webhooks/payfast')->and($query['signature'])->toHaveLength(32)
        ->and($checkout->providerReference)->toBe('REF1');
});

it('turns a confirmed COMPLETE notification into a payment event', function (): void {
    Http::fake(['sandbox.payfast.co.za/*' => Http::response('VALID')]);

    $event = payfast()->verifyWebhook(itn(), []);

    expect($event->type)->toBe(PaymentEventType::PaymentSucceeded)->and($event->providerReference)->toBe('REF1')
        ->and($event->eventId)->toBe('1089250')->and($event->amount->getMinorAmount()->toInt())->toBe(29_700);
    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/eng/query/validate'));
});

it('maps FAILED, CANCELLED and PENDING statuses', function (string $status, PaymentEventType $type): void {
    Http::fake(['*' => Http::response('VALID')]);

    expect(payfast()->verifyWebhook(itn(['payment_status' => $status]), [])->type)->toBe($type);
})->with([
    ['FAILED', PaymentEventType::PaymentFailed],
    ['CANCELLED', PaymentEventType::PaymentFailed],
    ['PENDING', PaymentEventType::PaymentPending],
]);

it('rejects a wrong signature, a tampered amount and another merchant', function (string $body): void {
    Http::fake(['*' => Http::response('VALID')]);

    expect(fn () => payfast()->verifyWebhook($body, []))->toThrow(InvalidWebhookSignature::class);
})->with([
    'wrong passphrase' => fn () => itn(passphrase: 'someone-elses'),
    'tampered amount' => fn () => str_replace('297.00', '1.00', itn()),
    'no signature' => fn () => preg_replace('/&signature=.*$/', '', itn()),
    'other merchant' => fn () => itn(['merchant_id' => '99999999']),
]);

it('rejects a notification PayFast does not confirm', function (): void {
    Http::fake(['*' => Http::response('INVALID')]);

    expect(fn () => payfast()->verifyWebhook(itn(), []))->toThrow(InvalidWebhookSignature::class);
});

it('rejects a notification from an address that is not PayFast\'s', function (): void {
    Http::fake(['*' => Http::response('VALID')]);

    expect(fn () => payfast(checkIp: true)->verifyWebhook(itn(), [PaymentGateway::SOURCE_IP_HEADER => '203.0.113.9']))->toThrow(InvalidWebhookSignature::class)
        ->and(fn () => payfast(checkIp: true)->verifyWebhook(itn(), []))->toThrow(InvalidWebhookSignature::class);
});

it('signs without a passphrase when none is set', function (): void {
    Http::fake(['*' => Http::response('VALID')]);
    $fields = ['m_payment_id' => 'REF1', 'pf_payment_id' => '1', 'payment_status' => 'COMPLETE', 'amount_gross' => '10.00', 'merchant_id' => PF_MERCHANT];
    $body = http_build_query($fields).'&signature='.md5(http_build_query($fields));

    expect(payfast(null)->verifyWebhook($body, [])->type)->toBe(PaymentEventType::PaymentSucceeded);
});

// --- The webhook route -----------------------------------------------------------------------

function pendingPayfastPurchase(): CreditPurchase
{
    $purchase = new CreditPurchase;
    $purchase->forceFill(['pro_id' => Pro::factory()->approved()->create()->id, 'amount_cents' => 29_700, 'status' => CreditPurchaseStatus::Pending])->save();

    return $purchase;
}

it('credits the pro when PayFast confirms the payment', function (): void {
    Http::fake(['*' => Http::response('VALID')]);
    app()->instance(PaymentGateway::class, payfast());
    $purchase = pendingPayfastPurchase();

    $this->call('POST', route('webhooks.payfast'), server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], content: itn(['m_payment_id' => $purchase->public_id]))->assertOk();
    $this->call('POST', route('webhooks.payfast'), server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], content: itn(['m_payment_id' => $purchase->public_id]))->assertOk();

    expect(app(ProCredit::class)->balanceCents($purchase->pro))->toBe(29_700)->and($purchase->refresh()->status)->toBe(CreditPurchaseStatus::Complete);
});

it('answers 400 and adds nothing for a forged notification', function (): void {
    Http::fake(['*' => Http::response('VALID')]);
    app()->instance(PaymentGateway::class, payfast());
    $purchase = pendingPayfastPurchase();

    $this->call('POST', route('webhooks.payfast'), server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], content: itn(['m_payment_id' => $purchase->public_id], passphrase: 'forged'))->assertStatus(400);

    expect(app(ProCredit::class)->balanceCents($purchase->pro))->toBe(0)->and($purchase->refresh()->status)->toBe(CreditPurchaseStatus::Pending);
});

it('puts the buyer\'s name and email on the PayFast link so they are not asked again', function (): void {
    $link = payfast()->createCheckout(new CheckoutRequest(Money::ofMinor(29_700, 'ZAR'), 'REF1', 'Credit', 'https://example.test/back', 'k1', 'Pat', 'pat@example.test'))->redirectUrl;
    parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

    expect($query['name_first'])->toBe('Pat')->and($query['email_address'])->toBe('pat@example.test')->and($query['return_url'])->toBe('https://example.test/back');

    $without = payfast()->createCheckout(new CheckoutRequest(Money::ofMinor(29_700, 'ZAR'), 'REF1', 'Credit', 'https://example.test/back', 'k1'))->redirectUrl;
    expect($without)->not->toContain('email_address');
});

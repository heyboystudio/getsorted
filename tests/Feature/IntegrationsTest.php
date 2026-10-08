<?php

declare(strict_types=1);

use App\Contracts\Data\ChatRequest;
use App\Contracts\Data\CheckoutRequest;
use App\Contracts\Data\PaymentEventType;
use App\Contracts\Data\PayoutRequest;
use App\Contracts\Data\RefundRequest;
use App\Contracts\Exceptions\InvalidWebhookSignature;
use App\Contracts\Geocoder;
use App\Contracts\PaymentGateway;
use App\Contracts\ScopingAssistant;
use App\Domain\Assistant\State\BookingState;
use App\Domain\Assistant\Support\BookingToolbox;
use App\Integrations\Fakes\FakeGeocoder;
use App\Integrations\Fakes\FakePaymentGateway;
use App\Integrations\Fakes\FakeScopingAssistant;
use Brick\Money\Money;

it('binds every contract to its fake, once per app, in tests', function (string $contract, string $fake): void {
    expect(app($contract))->toBeInstanceOf($fake)
        ->and(app($contract))->toBe(app($contract))
        ->and(new $fake)->toBeInstanceOf($contract);
})->with([
    [PaymentGateway::class, FakePaymentGateway::class],
    [ScopingAssistant::class, FakeScopingAssistant::class],
    [Geocoder::class, FakeGeocoder::class],
]);

it('creates hosted checkouts without moving money', function (): void {
    $gateway = new FakePaymentGateway;

    $checkout = $gateway->createCheckout(new CheckoutRequest(Money::of(500, 'ZAR'), 'JOB-1', 'Deposit', 'https://getsorted.test/return', 'deposit-job-1'));

    expect($checkout->redirectUrl)->toStartWith('https://payments.fake.test/checkout/');
    $gateway->assertCheckoutCreated(fn (CheckoutRequest $request): bool => $request->amount->isEqualTo(Money::of(500, 'ZAR')));
});

it('treats a repeated refund or payout instruction as one', function (): void {
    $gateway = new FakePaymentGateway;
    $refund = new RefundRequest('fake_chk_1', Money::of('250.00', 'ZAR'), 'cancelled', 'refund-job-1');
    $payout = new PayoutRequest('bank-token', Money::of('880.00', 'ZAR'), 'JOB-1', 'payout-job-1');

    $first = $gateway->refund($refund);
    $second = $gateway->refund($refund);
    $gateway->payout($payout);
    $gateway->payout($payout);

    expect($second->providerReference)->toBe($first->providerReference);
    $gateway->assertRefunded(Money::of('250.00', 'ZAR'), times: 1);
    $gateway->assertPaidOut(Money::of('880.00', 'ZAR'), times: 1);
});

it('accepts correctly signed webhooks', function (): void {
    $payload = json_encode(['id' => 'evt_1', 'type' => 'payment_succeeded', 'reference' => 'fake_chk_1', 'amount_cents' => 50000]);

    $event = (new FakePaymentGateway)->verifyWebhook($payload, [FakePaymentGateway::SIGNATURE_HEADER => FakePaymentGateway::sign($payload)]);

    expect($event->eventId)->toBe('evt_1')
        ->and($event->type)->toBe(PaymentEventType::PaymentSucceeded)
        ->and($event->amount->isEqualTo(Money::of(500, 'ZAR')))->toBeTrue();
});

it('rejects webhooks with a missing or wrong signature', function (array $headers): void {
    $payload = json_encode(['id' => 'evt_1', 'type' => 'payment_succeeded', 'reference' => 'fake_chk_1', 'amount_cents' => 50000]);

    (new FakePaymentGateway)->verifyWebhook($payload, $headers);
})->with([
    'missing' => [[]],
    'wrong' => [[FakePaymentGateway::SIGNATURE_HEADER => 'not-a-signature']],
])->throws(InvalidWebhookSignature::class);

it('gives a plain reply unless a test scripts the chat turn, and keeps the scripted tool calls honest', function (): void {
    $toolbox = new BookingToolbox(new BookingState, ['plumbing' => 'Plumbing'], [['role' => 'customer', 'text' => 'My geyser is leaking']]);
    $request = new ChatRequest($toolbox, [['role' => 'customer', 'text' => 'My geyser is leaking']]);
    $assistant = new FakeScopingAssistant;

    expect($assistant->chat($request)->reply)->toContain('Tell me a bit more');

    $assistant->willChat(function (ChatRequest $request): string {
        $request->toolbox->setTrade('plumbing');
        $request->toolbox->addFact('geyser is leaking', 'geyser is leaking');

        return 'Noted.';
    });

    expect($assistant->chat($request)->reply)->toBe('Noted.')
        ->and($toolbox->state->tradeKey)->toBe('plumbing')->and($toolbox->state->factTexts())->toBe(['geyser is leaking'])
        ->and($assistant->chatRequests())->toHaveCount(2);
});

it('looks up Durban addresses offline', function (): void {
    $geocoder = new FakeGeocoder;

    $suggestions = $geocoder->autocomplete('umhlanga', 'session-1');
    $address = $geocoder->resolve($suggestions[0]->placeId, 'session-1');

    expect($suggestions)->toHaveCount(1)
        ->and($address?->suburb)->toBe('Umhlanga')
        ->and($geocoder->autocomplete('nowhere', 'session-1'))->toBe([])
        ->and($geocoder->resolve('unknown', 'session-1'))->toBeNull();
});

<?php

declare(strict_types=1);

namespace App\Integrations\Fakes;

use App\Contracts\Data\Checkout;
use App\Contracts\Data\CheckoutRequest;
use App\Contracts\Data\PaymentEvent;
use App\Contracts\Data\PaymentEventType;
use App\Contracts\Data\PayoutRequest;
use App\Contracts\Data\ProviderResult;
use App\Contracts\Data\RefundRequest;
use App\Contracts\Exceptions\InvalidWebhookSignature;
use App\Contracts\PaymentGateway;
use Brick\Money\Money;
use Closure;
use JsonException;
use PHPUnit\Framework\Assert;

/**
 * In-memory payment provider for local development and tests. Never moves money.
 * Webhooks are signed with HMAC-SHA256 over the raw body using a fixed test secret.
 */
final class FakePaymentGateway implements PaymentGateway
{
    public const string WEBHOOK_SECRET = 'fake-webhook-secret';

    public const string SIGNATURE_HEADER = 'x-fake-signature';

    /** @var array<string, CheckoutRequest> keyed by idempotency key */
    private array $checkouts = [];

    /** @var array<string, RefundRequest> keyed by idempotency key */
    private array $refunds = [];

    /** @var array<string, PayoutRequest> keyed by idempotency key */
    private array $payouts = [];

    public function createCheckout(CheckoutRequest $request): Checkout
    {
        $this->checkouts[$request->idempotencyKey] ??= $request;
        $reference = 'fake_chk_'.substr(hash('sha256', $request->idempotencyKey), 0, 16);

        return new Checkout($reference, 'https://payments.fake.test/checkout/'.$reference);
    }

    public function verifyWebhook(string $payload, array $headers): PaymentEvent
    {
        $signature = $headers[self::SIGNATURE_HEADER] ?? '';

        if (! hash_equals(self::sign($payload), $signature)) {
            throw new InvalidWebhookSignature('Fake webhook signature mismatch.');
        }

        try {
            /** @var array{id: string, type: string, reference: string, amount_cents: int} $data */
            $data = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidWebhookSignature('Fake webhook body is not valid JSON.', $exception->getCode(), previous: $exception);
        }

        return new PaymentEvent(
            $data['id'],
            PaymentEventType::from($data['type']),
            $data['reference'],
            Money::ofMinor($data['amount_cents'], 'ZAR'),
        );
    }

    public function refund(RefundRequest $request): ProviderResult
    {
        $this->refunds[$request->idempotencyKey] ??= $request;

        return new ProviderResult('fake_ref_'.substr(hash('sha256', $request->idempotencyKey), 0, 16));
    }

    public function payout(PayoutRequest $request): ProviderResult
    {
        $this->payouts[$request->idempotencyKey] ??= $request;

        return new ProviderResult('fake_pay_'.substr(hash('sha256', $request->idempotencyKey), 0, 16));
    }

    /** Sign a webhook body the way the fake provider would, for tests and local tools. */
    public static function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, self::WEBHOOK_SECRET);
    }

    /** @param  (Closure(CheckoutRequest): bool)|null  $matches */
    public function assertCheckoutCreated(?Closure $matches = null): void
    {
        Assert::assertNotEmpty(array_filter($this->checkouts, $matches ?? fn (): bool => true), 'No matching checkout was created.');
    }

    public function assertRefunded(Money $amount, int $times = 1): void
    {
        $count = count(array_filter($this->refunds, fn (RefundRequest $refund): bool => $refund->amount->isEqualTo($amount)));
        Assert::assertSame($times, $count, "Expected {$times} refund(s) of {$amount}, found {$count}.");
    }

    public function assertNothingRefunded(): void
    {
        Assert::assertEmpty($this->refunds, 'Unexpected refunds were requested.');
    }

    public function assertPaidOut(Money $amount, int $times = 1): void
    {
        $count = count(array_filter($this->payouts, fn (PayoutRequest $payout): bool => $payout->amount->isEqualTo($amount)));
        Assert::assertSame($times, $count, "Expected {$times} payout(s) of {$amount}, found {$count}.");
    }

    public function assertNothingPaidOut(): void
    {
        Assert::assertEmpty($this->payouts, 'Unexpected payouts were requested.');
    }
}

<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Contracts\Data\Checkout;
use App\Contracts\Data\CheckoutRequest;
use App\Contracts\Data\PaymentEvent;
use App\Contracts\Data\PayoutRequest;
use App\Contracts\Data\ProviderResult;
use App\Contracts\Data\RefundRequest;
use App\Contracts\Exceptions\InvalidWebhookSignature;

/**
 * The payment provider (chosen in Phase 4; shortlist in docs/product/money-flow.md).
 * Every instruction carries an idempotency key: repeating it must not move money twice.
 * This interface is provisional and will be refined when the provider is chosen.
 */
interface PaymentGateway
{
    /** The webhook header carrying the sender's IP address, for providers that verify it (PayFast). */
    public const string SOURCE_IP_HEADER = 'x-source-ip';

    public function createCheckout(CheckoutRequest $request): Checkout;

    /**
     * @param  array<string, string>  $headers  lower-cased header names
     *
     * @throws InvalidWebhookSignature
     */
    public function verifyWebhook(string $payload, array $headers): PaymentEvent;

    public function refund(RefundRequest $request): ProviderResult;

    public function payout(PayoutRequest $request): ProviderResult;
}

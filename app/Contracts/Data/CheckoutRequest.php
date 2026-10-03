<?php

declare(strict_types=1);

namespace App\Contracts\Data;

use Brick\Money\Money;

/** A hosted-checkout payment the customer is sent to (Sortd never sees card details). */
final readonly class CheckoutRequest
{
    public function __construct(
        public Money $amount,
        public string $reference,
        public string $description,
        public string $returnUrl,
        public string $idempotencyKey,
    ) {}
}

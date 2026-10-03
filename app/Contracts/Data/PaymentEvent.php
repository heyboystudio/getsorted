<?php

declare(strict_types=1);

namespace App\Contracts\Data;

use Brick\Money\Money;

/** A verified provider webhook. Payment state changes only from these (money-flow principle 3). */
final readonly class PaymentEvent
{
    public function __construct(
        public string $eventId,
        public PaymentEventType $type,
        public string $providerReference,
        public Money $amount,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Contracts\Data;

/** Acknowledgement of a refund or payout instruction; the outcome arrives later as a PaymentEvent. */
final readonly class ProviderResult
{
    public function __construct(
        public string $providerReference,
    ) {}
}

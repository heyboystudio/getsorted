<?php

declare(strict_types=1);

namespace App\Contracts\Data;

use Brick\Money\Money;

/** Money released to a pro. `recipientReference` is the provider's token for the pro's bank account. */
final readonly class PayoutRequest
{
    public function __construct(
        public string $recipientReference,
        public Money $amount,
        public string $reference,
        public string $idempotencyKey,
    ) {}
}

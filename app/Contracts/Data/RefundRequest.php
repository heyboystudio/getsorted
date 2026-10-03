<?php

declare(strict_types=1);

namespace App\Contracts\Data;

use Brick\Money\Money;

final readonly class RefundRequest
{
    public function __construct(
        public string $paymentReference,
        public Money $amount,
        public string $reason,
        public string $idempotencyKey,
    ) {}
}

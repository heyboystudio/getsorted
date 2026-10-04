<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Data;

/** Server-calculated quote amounts in integer cents (spec 010, AC2). */
final readonly class QuoteTotals
{
    /**
     * @param  list<int>  $lineTotalsCents
     */
    public function __construct(
        public array $lineTotalsCents,
        public int $labourCents,
        public int $materialsCents,
        public int $calloutCents,
        public int $vatCents,
        public int $totalCents,
        public int $depositCents,
        public int $commissionEstimateCents,
        public int $payoutEstimateCents,
    ) {}
}

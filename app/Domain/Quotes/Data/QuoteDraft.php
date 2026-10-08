<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Data;

use Carbon\CarbonImmutable;

/** What a pro enters; every amount is recalculated on the server (spec 010, AC2). */
final readonly class QuoteDraft
{
    /**
     * @param  list<QuoteLineData>  $lines
     */
    public function __construct(
        public array $lines,
        public int $depositPercent,
        public CarbonImmutable $earliestStartDate,
        public int $validityDays,
        public ?string $notes,
        public ?int $highTotalCents = null,
    ) {}
}

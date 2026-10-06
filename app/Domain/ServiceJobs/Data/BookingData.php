<?php

declare(strict_types=1);

namespace App\Domain\ServiceJobs\Data;

use App\Domain\ServiceJobs\Enums\TimeWindow;
use Carbon\CarbonImmutable;

/** What the booking conversation has collected so far; all fields optional until posting. */
final readonly class BookingData
{
    /**
     * @param  list<array{id: string, text: string, turn: int}>  $facts  short facts Siya extracted from the customer's words
     */
    public function __construct(
        public array $facts = [],
        public ?string $notes = null,
        public ?string $propertyPublicId = null,
        public ?CarbonImmutable $preferredDate = null,
        public ?TimeWindow $timeWindow = null,
        public bool $urgent = false,
    ) {}
}
